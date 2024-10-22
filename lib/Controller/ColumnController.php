<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\Service\ColumnService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ColumnController extends Controller {
	private ColumnService $service;

	private string $userId;

	protected LoggerInterface $logger;

	use Errors;

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		ColumnService $service,
		string $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->logger = $logger;
		$this->service = $service;
		$this->userId = $userId;
	}

	#[NoAdminRequired]
	public function index(int $tableId, ?int $viewId): DataResponse {
		return $this->handleError(function () use ($tableId, $viewId) {
			return $this->service->findAllByTable($tableId, $viewId);
		});
	}

	#[NoAdminRequired]
	public function indexTableByView(int $tableId, ?int $viewId): DataResponse {
		return $this->handleError(function () use ($tableId, $viewId) {
			return $this->service->findAllByTable($tableId, $viewId);
		});
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	public function indexView(int $viewId): DataResponse {
		return $this->handleError(function () use ($viewId) {
			return $this->service->findAllByView($viewId);
		});
	}

	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->find($id);
		});
	}

	#[NoAdminRequired]
	public function create(
		?int $tableId,
		?int $viewId,
		string $type,
		?string $subtype,
		string $title,
		bool $mandatory,
		?string $description,

		?string $textDefault,
		?string $textAllowedPattern,
		?int $textMaxLength,

		?string $numberPrefix,
		?string $numberSuffix,
		?float $numberDefault,
		?float $numberMin,
		?float $numberMax,
		?int $numberDecimals,

		?string $selectionOptions,
		?string $selectionDefault,

		?string $datetimeDefault,

		?string $usergroupDefault,
		?bool $usergroupMultipleItems,
		?bool $usergroupSelectUsers,
		?bool $usergroupSelectGroups,
		?bool $showUserStatus,

		?array $selectedViewIds
	): DataResponse {
		return $this->handleError(function () use (
			$tableId,
			$viewId,
			$type,
			$subtype,
			$title,
			$mandatory,
			$description,

			$textDefault,
			$textAllowedPattern,
			$textMaxLength,

			$numberPrefix,
			$numberSuffix,
			$numberDefault,
			$numberMin,
			$numberMax,
			$numberDecimals,

			$selectionOptions,
			$selectionDefault,

			$datetimeDefault,

			$usergroupDefault,
			$usergroupMultipleItems,
			$usergroupSelectUsers,
			$usergroupSelectGroups,
			$showUserStatus,

			$selectedViewIds) {
			return $this->service->create(
				$this->userId,
				$tableId,
				$viewId,
				new ColumnDto(
					title: $title,
					type: $type,
					subtype: $subtype,
					mandatory: $mandatory,
					description: $description,
					textDefault: $textDefault,
					textAllowedPattern: $textAllowedPattern,
					textMaxLength: $textMaxLength,
					numberDefault: $numberDefault,
					numberMin: $numberMin,
					numberMax: $numberMax,
					numberDecimals: $numberDecimals,
					numberPrefix: $numberPrefix,
					numberSuffix: $numberSuffix,
					selectionOptions: $selectionOptions,
					selectionDefault: $selectionDefault,
					datetimeDefault: $datetimeDefault,
					usergroupDefault: $usergroupDefault,
					usergroupMultipleItems: $usergroupMultipleItems,
					usergroupSelectUsers: $usergroupSelectUsers,
					usergroupSelectGroups: $usergroupSelectGroups,
					showUserStatus: $showUserStatus
				),
				$selectedViewIds
			);
		});
	}

	#[NoAdminRequired]
	public function update(
		int $id,
		?int $tableId,
		?string $type,
		?string $subtype,
		?string $title,
		?bool $mandatory,
		?string $description,

		?string $textDefault,
		?string $textAllowedPattern,
		?int $textMaxLength,

		?string $numberPrefix,
		?string $numberSuffix,
		?float $numberDefault,
		?float $numberMin,
		?float $numberMax,
		?int $numberDecimals,

		?string $selectionOptions,
		?string $selectionDefault,

		?string $datetimeDefault,

		?string $usergroupDefault,
		?bool $usergroupMultipleItems,
		?bool $usergroupSelectUsers,
		?bool $usergroupSelectGroups,
		?bool $showUserStatus
	): DataResponse {
		return $this->handleError(function () use (
			$id,
			$tableId,
			$type,
			$subtype,
			$title,
			$mandatory,
			$description,

			$textDefault,
			$textAllowedPattern,
			$textMaxLength,

			$numberPrefix,
			$numberSuffix,
			$numberDefault,
			$numberMin,
			$numberMax,
			$numberDecimals,

			$selectionOptions,
			$selectionDefault,

			$datetimeDefault,

			$usergroupDefault,
			$usergroupMultipleItems,
			$usergroupSelectUsers,
			$usergroupSelectGroups,
			$showUserStatus
		) {
			return $this->service->update(
				$id,
				$tableId,
				$this->userId,
				new ColumnDto(
					title: $title,
					type: $type,
					subtype: $subtype,
					mandatory: $mandatory,
					description: $description,
					textDefault: $textDefault,
					textAllowedPattern: $textAllowedPattern,
					textMaxLength: $textMaxLength,
					numberDefault: $numberDefault,
					numberMin: $numberMin,
					numberMax: $numberMax,
					numberDecimals: $numberDecimals,
					numberPrefix: $numberPrefix,
					numberSuffix: $numberSuffix,
					selectionOptions: $selectionOptions,
					selectionDefault: $selectionDefault,
					datetimeDefault: $datetimeDefault,
					usergroupDefault: $usergroupDefault,
					usergroupMultipleItems: $usergroupMultipleItems,
					usergroupSelectUsers: $usergroupSelectUsers,
					usergroupSelectGroups: $usergroupSelectGroups,
					showUserStatus: $showUserStatus
				)
			);
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->delete($id, false, $this->userId);
		});
	}
}
