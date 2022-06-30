<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ShareMapper extends QBMapper {
    protected $table = 'tables_shares';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, Share::class);
	}

    /**
     * @param int $id
     * @return Entity|Share
     * @throws MultipleObjectsReturnedException
     * @throws Exception
     * @throws DoesNotExistException
     */
    public function find(int $id): Share {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->table)
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     * @throws Exception
     */
    public function findShareForNodeId($user, int $nodeId): Share
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->table)
            ->where($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('receiver', $qb->createNamedParameter($user, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * @param $nodeType
     * @param $receiver
     * @param string $receiverType
     * @return Share[]
     * @throws Exception
     */
    public function findAllSharesFor($nodeType, $receiver, $receiverType='user'): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->table)
            ->where($qb->expr()->eq('receiver', $qb->createNamedParameter($receiver, IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->eq('receiver_type', $qb->createNamedParameter($receiverType, IQueryBuilder::PARAM_STR)));
        return $this->findEntities($qb);
    }

    /**
     * @param $nodeType
     * @param int $nodeId
     * @param $sender
     * @return array
     * @throws Exception
     */
    public function findAllSharesForNode($nodeType, int $nodeId, $sender): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->table)
            ->where($qb->expr()->eq('sender', $qb->createNamedParameter($sender, IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)));
        return $this->findEntities($qb);
    }
}
