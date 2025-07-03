<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/** @template-extends QBMapper<RowSleeve> */
class RowSleeveMapper extends QBMapper {
	protected string $table = 'tables_row_sleeves';
	protected LoggerInterface $logger;

	public function __construct(IDBConnection $db, LoggerInterface $logger) {
		parent::__construct($db, $this->table, RowSleeve::class);
		$this->logger = $logger;
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function find(int $id): RowSleeve {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @param int[] $ids
	 * @return RowSleeve[]
	 * @throws Exception
	 */
	public function findMultiple(array $ids): array {
		$sleeveAlias = 'sleeves';
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			$sleeveAlias . '.id',
			$sleeveAlias . '.table_id',
			$sleeveAlias . '.created_by',
			$sleeveAlias . '.created_at',
			$sleeveAlias . '.last_edit_by',
			$sleeveAlias . '.last_edit_at',
		)
			->from($this->table, $sleeveAlias)
			->where($qb->expr()->in($sleeveAlias . '.id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
		return $this->findEntities($qb);
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function findNext(int $offsetId = -1): RowSleeve {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->gt('id', $qb->createNamedParameter($offsetId)))
			->setMaxResults(1)
			->orderBy('id', 'ASC');

		return $this->findEntity($qb);
	}

	/**
	 * @param int $sleeveId
	 * @throws Exception
	 */
	public function deleteById(int $sleeveId) {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($sleeveId, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
	}

	/**
	 * @param int $tableId
	 * @return int Effected rows
	 * @throws Exception
	 */
	public function deleteAllForTable(int $tableId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where(
				$qb->expr()->eq('table_id', $qb->createNamedParameter($tableId, IQueryBuilder::PARAM_INT))
			);
		return $qb->executeStatement();
	}

	/**
	 * @param int $tableId
	 * @return int
	 */
	public function countRows(int $tableId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'counter'));
		$qb->from($this->table, 't1');
		$qb->where(
			$qb->expr()->eq('table_id', $qb->createNamedParameter($tableId))
		);

		try {
			$result = $this->findOneQuery($qb);
			return (int)$result['counter'];
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			$this->logger->warning('Exception occurred: ' . $e->getMessage() . ' Will return 0.');
			return 0;
		}
	}
}
