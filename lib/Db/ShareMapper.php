<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Share> */
class ShareMapper extends QBMapper {
	protected string $table = 'tables_shares';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, Share::class);
	}

	/**
	 * @param int $id
	 *
	 * @return Share
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id): Share {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * find share for a node
	 * look for all receiver types or limit it to one given type
	 *
	 * @param int $nodeId
	 * @param string $nodeType
	 * @param string $receiver
	 * @param string|null $receiverType
	 *
	 * @return Share
	 *
	 * @throws Exception
	 */
	public function findShareForNode(int $nodeId, string $nodeType, string $receiver, ?string $receiverType = null): Share {
		// if shared with user
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('receiver', $qb->createNamedParameter($receiver, IQueryBuilder::PARAM_STR)));
		if ($receiverType) {
			$qb->andWhere($qb->expr()->eq('receiver_type', $qb->createNamedParameter($receiverType, IQueryBuilder::PARAM_STR)));
		}
		try {
			$items = $this->findEntities($qb);
			if (count($items) > 0) {
				return $items[0];
			}
		} catch (Exception $e) {
		}

		throw new Exception('no shares found as expected');
	}

	/**
	 * @param string $nodeType
	 * @param string $receiver
	 * @param string|null $receiverType
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public function findAllSharesFor(string $nodeType, string $receiver, ?string $receiverType = 'user'): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('receiver', $qb->createNamedParameter($receiver, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('receiver_type', $qb->createNamedParameter($receiverType, IQueryBuilder::PARAM_STR)));
		return $this->findEntities($qb);
	}

	/**
	 * @param string $nodeType
	 * @param int $nodeId
	 * @param string $sender
	 * @param int|null $limit
	 * @return array
	 * @throws Exception
	 */
	public function findAllSharesForNode(string $nodeType, int $nodeId, string $sender, ?int $limit = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('sender', $qb->createNamedParameter($sender, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	public function findAllSharesForNodeFor(string $nodeType, int $nodeId, string $receiver, ?string $receiverType = 'user'): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('receiver', $qb->createNamedParameter($receiver, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('receiver_type', $qb->createNamedParameter($receiverType, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * @param int $nodeId
	 * @param string $nodeType
	 * @throws Exception
	 */
	public function deleteByNode(int $nodeId, string $nodeType):void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_STR)))
			->executeStatement();
	}
}
