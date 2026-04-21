<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\Model\ColumnSettings;
use OCA\Tables\Model\SortRuleSet;
use OCA\Tables\Service\TableService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class TableController extends Controller {
	private TableService $service;

	private string $userId;

	protected LoggerInterface $logger;

	use Errors;


	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		TableService $service,
		string $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->logger = $logger;
		$this->service = $service;
		$this->userId = $userId;
	}


	#[NoAdminRequired]
	public function index(): DataResponse {
		return $this->handleError(function () {
			return $this->service->findAll($this->userId);
		});
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_TABLE, idParam: 'id')]
	public function show(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->find($id);
		});
	}

	#[NoAdminRequired]
	public function create(string $title, string $template, string $emoji): DataResponse {
		return $this->handleError(function () use ($title, $template, $emoji) {
			return $this->service->create($title, $template, $emoji);
		});
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_TABLE, idParam: 'id')]
	public function destroy(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->delete($id);
		});
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_TABLE, idParam: 'id')]
	public function update(int $id, ?string $title = null, ?string $emoji = null, ?bool $archived = null, null|array|string $columnSettings = null, null|array|string $sort = null): DataResponse {
		if (is_string($columnSettings)) {
			$columnSettings = json_decode($columnSettings, true) ?? null;
		}
		if (is_string($sort)) {
			$sort = json_decode($sort, true) ?? null;
		}
		return $this->handleError(function () use ($id, $title, $emoji, $archived, $columnSettings, $sort) {
			if ($columnSettings !== null) {
				if (!is_array($columnSettings)) {
					throw new \InvalidArgumentException('Invalid columnSettings: must be a JSON array');
				}
				ColumnSettings::createFromInputArray($columnSettings);
			}
			if ($sort !== null) {
				if (!is_array($sort)) {
					throw new \InvalidArgumentException('Invalid sort: must be a JSON array');
				}
				SortRuleSet::createFromInputArray($sort);
			}
			return $this->service->update($id, $title, $emoji, null, $archived, $this->userId, $columnSettings, $sort);
		});
	}
}
