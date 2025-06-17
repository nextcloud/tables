<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Api;

use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\RowService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class V1Api {
	private RowService $rowService;
	private ColumnService $columnService;
	private ?string $userId;

	public function __construct(ColumnService $columnService, RowService $rowService, ?string $userId) {
		$this->columnService = $columnService;
		$this->rowService = $rowService;
		$this->userId = $userId;
	}

	/**
	 * @param int $nodeId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @param string|null $userId
	 * @param string|null $nodeType
	 * @return array
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function getData(int $nodeId, ?int $limit, ?int $offset, ?string $userId, ?string $nodeType = null): array {
		if ($userId) {
			$this->userId = $userId;
		}
		if ($nodeType === 'view') {
			$columns = $this->columnService->findAllByView($nodeId, $this->userId);
			$rows = $this->rowService->findAllByView($nodeId, $this->userId, $limit, $offset);
		} else {
			// if no nodeType is provided, the old table selection is used to not break anything
			$columns = $this->columnService->findAllByTable($nodeId, $this->userId);
			$rows = $this->rowService->findAllByTable($nodeId, $this->userId, $limit, $offset);
		}

		$data = [];

		// first line contains the titles
		$header = [];
		foreach ($columns as $column) {
			$header[] = $column->getTitle();
		}
		$data[] = $header;

		// now add the rows
		foreach ($rows as $row) {
			$rowData = $row->getData();
			$line = [];
			foreach ($columns as $column) {
				$value = '';
				foreach ($rowData as $datum) {
					if ($datum['columnId'] === $column->getId()) {
						$value = $datum['value'];
					}
				}
				$line[] = $value;
			}
			$data[] = $line;
		}

		return $data;
	}
}
