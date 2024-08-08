<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Db\View;
use OCA\Tables\Errors\PermissionError;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use Psr\Log\LoggerInterface;

class SearchService extends SuperService {

	private RowService $rowService;
	private TableService $tableService;
	private ViewService $viewService;

	public function __construct(PermissionsService $permissionsService, LoggerInterface $logger, ?string $userId, RowService $rowService, TableService $tableService, ViewService $viewService) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->rowService = $rowService;
		$this->tableService = $tableService;
		$this->viewService = $viewService;
	}

	public function all(string $term = ''): array {
		if ($term === '') {
			return [];
		}

		$viewResults = $this->getAllViewResults($term);

		try {
			$tableResults = $this->getAllTableResults($term);
		} catch (PermissionError|MultipleObjectsReturnedException $e) {
			$this->logger->warning('Could not load tables results in search', ['e' => $e]);
			$tableResults = [];
		}

		return array_merge($viewResults, $tableResults);
		// return [];
	}

	private function getAllViewResults(string $term): array {
		$return = [];
		$results = $this->viewService->search($term);

		foreach ($results as $result) {
			/** @var View $result */
			$return[] = [
				'label' => $result->getTitle(),
				'value' => $result->getId(),
				'quality' => 0,
				'type' => 'view',
				'emoji' => $result->getEmoji(),
				'owner' => $result->getOwnership(),
				'ownerDisplayName' => $result->getOwnerDisplayName(),
				'rowsCount' => $result->getRowsCount(),
			];
		}

		return $return;
	}

	/**
	 * @throws PermissionError
	 * @throws MultipleObjectsReturnedException
	 */
	private function getAllTableResults(string $term): array {
		$return = [];
		$results = $this->tableService->search($term);

		foreach ($results as $result) {
			/** @var View $result */
			$return[] = [
				'label' => $result->getTitle(),
				'value' => $result->getId(),
				'quality' => 0,
				'type' => 'table',
				'emoji' => $result->getEmoji(),
				'owner' => $result->getOwnership(),
				'ownerDisplayName' => $result->getOwnerDisplayName(),
				'rowsCount' => $result->getRowsCount(),
			];
		}

		return $return;
	}

}
