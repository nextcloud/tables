<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\Service\TableService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class TableController extends Controller {
	use Errors;


	public function __construct(
		IRequest $request,
		protected LoggerInterface $logger,
		private TableService $service,
		private string $userId) {
		parent::__construct(Application::APP_ID, $request);
	}


	#[NoAdminRequired]
	public function index(): DataResponse {
		return $this->handleError(fn() => $this->service->findAll($this->userId));
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_TABLE, idParam: 'id')]
	public function show(int $id): DataResponse {
		return $this->handleError(fn() => $this->service->find($id));
	}

	#[NoAdminRequired]
	public function create(string $title, string $template, string $emoji): DataResponse {
		return $this->handleError(fn() => $this->service->create($title, $template, $emoji));
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_TABLE, idParam: 'id')]
	public function destroy(int $id): DataResponse {
		return $this->handleError(fn() => $this->service->delete($id));
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_TABLE, idParam: 'id')]
	public function update(int $id, ?string $title = null, ?string $emoji = null, ?bool $archived = null): DataResponse {
		return $this->handleError(fn() => $this->service->update($id, $title, $emoji, null, $archived, $this->userId));
	}
}
