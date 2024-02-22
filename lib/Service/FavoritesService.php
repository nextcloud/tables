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

use OCP\Cache\CappedMemoryCache;
use OCP\IDBConnection;

class FavoritesService {

	private CappedMemoryCache $cache;

	public function __construct(private IDBConnection $connection, private ?string $userId) {
		$this->cache = new CappedMemoryCache();
	}

	public function isFavorite(int $nodeType, int $id): bool {
		if ($cached = $this->cache->get($this->userId . '_' . $nodeType . '_' . $id)) {
			return $cached;
		}

		// We still might run this multiple times

		$qb = $this->connection->getQueryBuilder();
		$qb->select('*')
			->from('tables_favorites')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)));

		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$this->cache->set($this->userId . '_' . $row['node_type'] . '_' . $row['node_id'], true);
		}

		return $this->cache->get($this->userId . '_' . $nodeType . '_' . $id) ?? false;
	}

	public function addFavorite(int $nodeType, int $id): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('tables_favorites')
			->values([
				'user_id' => $qb->createNamedParameter($this->userId),
				'node_type' => $qb->createNamedParameter($nodeType),
				'node_id' => $qb->createNamedParameter($id),
			]);
		$qb->executeStatement();
		$this->cache->set($this->userId . '_' . $nodeType . '_' . $id, true);
	}

	public function removeFavorite(int $nodeType, int $id): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('tables_favorites')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType)))
			->andWhere($qb->expr()->eq('node_id', $qb->createNamedParameter($id)));
		$qb->executeStatement();
		$this->cache->set($this->userId . '_' . $nodeType . '_' . $id, false);
	}

}
