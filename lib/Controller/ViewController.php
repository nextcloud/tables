<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
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


	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		ViewService $service,
		ViewMapper $mapper,
		TableService $tableService,
		string $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->logger = $logger;
		$this->service = $service;
		$this->mapper = $mapper;
		$this->tableService = $tableService;
		$this->userId = $userId;
	}


	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	public function index(int $tableId): DataResponse {
		return $this->handleError(function () use ($tableId) {
			return $this->service->findAll($this->getTable($tableId), $this->userId);
		});
	}

	#[NoAdminRequired]
	public function indexSharedWithMe(): DataResponse {
		return $this->handleError(function () {
			return $this->service->findSharedViewsWithMe($this->userId);
		});
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_VIEW, idParam: 'id')]
	public function show(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->find($id);
		});
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	public function create(int $tableId, string $title, ?string $emoji): DataResponse {
		return $this->handleError(function () use ($tableId, $title, $emoji) {
			return $this->service->create($title, $emoji, $this->getTable($tableId, true));
		});
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_VIEW, idParam: 'id')]
	public function update(int $id, array $data): DataResponse {
		return $this->handleError(function () use ($id, $data) {
			return $this->service->update($id, $data, $this->userId);
		});
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_VIEW, idParam: 'id')]
	public function destroy(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->delete($id);
		});
	}

	/**
	 * @param int $tableId
	 * @param bool $skipTableEnhancement
	 * @return Table
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	private function getTable(int $tableId, bool $skipTableEnhancement = false): Table {
		try {
			return $this->tableService->find($tableId, $skipTableEnhancement);
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
}
