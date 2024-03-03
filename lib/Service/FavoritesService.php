<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Tables\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCP\Cache\CappedMemoryCache;
use OCP\DB\Exception;
use OCP\IDBConnection;

class FavoritesService {

	private IDBConnection $connection;
	private PermissionsService $permissionsService;
	private ?string $userId;
	private CappedMemoryCache $cache;

	public function __construct(
		IDBConnection $connection,
		PermissionsService $permissionsService,
		?string $userId
	) {
		$this->connection = $connection;
		$this->permissionsService = $permissionsService;
		$this->userId = $userId;
		// The cache usage is currently not unique to the user id as only a memory cache is used
		$this->cache = new CappedMemoryCache();
	}

	public function isFavorite(int $nodeType, int $id): bool {
		$cacheKey = $nodeType . '_' . $id;
		if ($cached = $this->cache->get($cacheKey)) {
			return $cached;
		}

		$this->cache->clear();
		$qb = $this->connection->getQueryBuilder();
		$qb->select('*')
			->from('tables_favorites')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)));

		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$this->cache->set($row['node_type'] . '_' . $row['node_id'], true);
		}

		// Set the cache for not found matches still to avoid further queries
		if (!$this->cache->get($cacheKey)) {
			$this->cache->set($cacheKey, false);
		}

		return $this->cache->get($cacheKey);
	}

	/**
	 * @throws Exception
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function addFavorite(int $nodeType, int $id): void {
		$this->checkValidNodeType($nodeType);
		$this->checkAccessToNode($nodeType, $id);

		$qb = $this->connection->getQueryBuilder();
		$qb->insert('tables_favorites')
			->values([
				'user_id' => $qb->createNamedParameter($this->userId),
				'node_type' => $qb->createNamedParameter($nodeType),
				'node_id' => $qb->createNamedParameter($id),
			]);
		$qb->executeStatement();
		$this->cache->set($nodeType . '_' . $id, true);
	}

	/**
	 * @throws Exception
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function removeFavorite(int $nodeType, int $id): void {
		$this->checkValidNodeType($nodeType);
		$this->checkAccessToNode($nodeType, $id);

		$qb = $this->connection->getQueryBuilder();
		$qb->delete('tables_favorites')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType)))
			->andWhere($qb->expr()->eq('node_id', $qb->createNamedParameter($id)));
		$qb->executeStatement();
		$this->cache->set($nodeType . '_' . $id, false);
	}

	/**
	 * @throws InternalError
	 */
	private function checkValidNodeType(int $nodeType): void {
		if (!in_array($nodeType, [Application::NODE_TYPE_TABLE, Application::NODE_TYPE_VIEW])) {
			throw new InternalError('Invalid node type');
		}
	}

	/**
	 * @throws PermissionError
	 */
	private function checkAccessToNode(int $nodeType, int $nodeId): void {
		if ($this->permissionsService->canAccessNodeById($nodeType, $nodeId)) {
			return;
		}

		throw new PermissionError('Invalid node type and id');
	}

}
