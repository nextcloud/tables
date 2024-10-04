<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Db;

use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<ContextNavigation> */
class ContextNavigationMapper extends QBMapper {
	protected string $table = 'tables_contexts_navigation';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, ContextNavigation::class);
	}

	/**
	 * @throws Exception
	 */
	public function deleteByShareId(int $shareId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->eq('share_id', $qb->createNamedParameter($shareId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function setDisplayModeByShareId(int $shareId, int $displayMode, string $userId): ContextNavigation {
		$entity = new ContextNavigation();
		$entity->setShareId($shareId);
		$entity->setDisplayMode($displayMode);
		$entity->setUserId($userId);

		return $this->insertOrUpdate($entity);
	}

	// we have to overwrite QBMapper`s insert() because we do not have
	// an id column in this table. Sad.
	public function insert(Entity $entity): Entity {
		// get updated fields to save, fields have to be set using a setter to
		// be saved
		$properties = $entity->getUpdatedFields();

		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->tableName);

		// build the fields
		foreach ($properties as $property => $updated) {
			$column = $entity->propertyToColumn($property);
			$getter = 'get' . ucfirst($property);
			$value = $entity->$getter();

			$type = $this->getParameterTypeForProperty($entity, $property);
			$qb->setValue($column, $qb->createNamedParameter($value, $type));
		}

		$qb->executeStatement();

		return $entity;
	}

	// we have to overwrite QBMapper`s update() because we do not have
	// an id column in this table. Sad.
	public function update(Entity $entity): ContextNavigation {
		if (!$entity instanceof ContextNavigation) {
			throw new \LogicException('Can only update context navigation entities');
		}

		// if entity wasn't changed it makes no sense to run a db query
		$properties = $entity->getUpdatedFields();
		if (\count($properties) === 0) {
			return $entity;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName);

		// build the fields
		foreach ($properties as $property => $updated) {
			$column = $entity->propertyToColumn($property);
			$getter = 'get' . ucfirst($property);
			$value = $entity->$getter();

			$type = $this->getParameterTypeForProperty($entity, $property);
			$qb->set($column, $qb->createNamedParameter($value, $type));
		}

		$qb->where($qb->expr()->eq('share_id', $qb->createNamedParameter($entity->getShareId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($entity->getUserId(), IQueryBuilder::PARAM_STR)));

		$qb->executeStatement();

		return $entity;
	}
}
