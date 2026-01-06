<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\ConversionHelper;
class NodeService {
	private const PUBLIC_NODE_KEYS = [
		'title',
		'emoji',
		'description',
		'createdAt',
		'lastEditAt',
		'rowsCount',
	];

	public function __construct(
		private readonly TableService $tableService,
		private readonly ViewService $viewService,
	) {
	}

	/**
	 * @param 'table'|'view' $nodeType
	 * @param int $nodeId
	 * @return array{title: string, emoji: string, description: string, createdAt: string, lastEditAt: string, rowsCount: int}
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function getPublicDataOfNode(string $nodeType, int $nodeId): array {
		$type = ConversionHelper::stringNodeType2Const($nodeType);
		if ($type === Application::NODE_TYPE_TABLE) {
			return $this->publicDataOfTable($nodeId);
		}
		if ($type === Application::NODE_TYPE_VIEW) {
			return $this->publicDataOfView($nodeId);
		}
		throw new InternalError('Unreachable');
	}

	/**
	 * @return array{title: string, emoji: string, description: string, createdAt: string, lastEditAt: string, rowsCount: int}
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	private function publicDataOfTable(int $id): array {
		$table = $this->tableService->find($id, false, '');
		/** @var array{title: string, emoji: string, description: string, createdAt: string, lastEditAt: string, rowsCount: int} */
		return array_filter($table->jsonSerialize(), static function (string $key): bool {
			return in_array($key, self::PUBLIC_NODE_KEYS, true);
		}, ARRAY_FILTER_USE_KEY);
	}

	/**
	 * @return array{title: string, emoji: string, description: string, createdAt: string, lastEditAt: string, rowsCount: int}
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	private function publicDataOfView(int $id): array {
		$view = $this->viewService->find($id, false, '');
		/** @var array{title: string, emoji: string, description: string, createdAt: string, lastEditAt: string, rowsCount: int} */
		return array_filter($view->jsonSerialize(), static function (string $key): bool {
			return in_array($key, self::PUBLIC_NODE_KEYS, true);
		}, ARRAY_FILTER_USE_KEY);
	}
}
