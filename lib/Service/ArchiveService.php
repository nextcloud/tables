<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Context;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\UserArchiveMapper;
use OCA\Tables\Errors\InternalError;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ArchiveService {

	public function __construct(
		private IDBConnection $connection,
		private UserArchiveMapper $userArchiveMapper,
	) {
	}

	/**
	 * Archive a table or context for a user.
	 *
	 * If the user is the owner, the entity-level flag is set to true and all
	 * per-user overrides are cleared (everyone inherits the owner's choice).
	 * If the user is not the owner, a personal override is upserted.
	 *
	 * @throws Exception
	 * @throws InternalError
	 */
	public function archiveForUser(string $userId, int $nodeType, int $nodeId, bool $isOwner): void {
		if ($isOwner) {
			$this->setEntityArchived($nodeType, $nodeId, true);
			$this->userArchiveMapper->deleteAllForNode($nodeType, $nodeId);
		} else {
			$this->userArchiveMapper->upsert($userId, $nodeType, $nodeId, true);
		}
	}

	/**
	 * Unarchive a table or context for a user.
	 *
	 * If the user is the owner, the entity-level flag is set to false and all
	 * per-user overrides are cleared (resets everyone to "not archived").
	 * If the user is not the owner and the entity is not owner-archived, the
	 * personal override is simply removed.
	 * If the user is not the owner but the entity is owner-archived, an
	 * explicit unarchive override is upserted so the user sees the item in
	 * their active list while the owner's state is preserved for others.
	 *
	 * @throws Exception
	 * @throws InternalError
	 */
	public function unarchiveForUser(string $userId, int $nodeType, int $nodeId, bool $isOwner, bool $entityArchived): void {
		if ($isOwner) {
			$this->setEntityArchived($nodeType, $nodeId, false);
			$this->userArchiveMapper->deleteAllForNode($nodeType, $nodeId);
		} elseif (!$entityArchived) {
			// Entity is not owner-archived — just remove the personal override.
			$this->userArchiveMapper->deleteForUser($userId, $nodeType, $nodeId);
		} else {
			// Entity is owner-archived — store an explicit unarchive override so
			// the user's active list shows the item while the owner's state is
			// preserved for everyone else.
			$this->userArchiveMapper->upsert($userId, $nodeType, $nodeId, false);
		}
	}

	/**
	 * Resolve the effective archive state for a user.
	 *
	 * A personal override (if present) takes precedence over the entity flag.
	 *
	 * @throws Exception
	 */
	public function isArchivedForUser(string $userId, int $nodeType, int $nodeId, bool $entityArchived): bool {
		$override = $this->userArchiveMapper->findForUser($userId, $nodeType, $nodeId);
		return $override !== null ? $override->isArchived() : $entityArchived;
	}

	/**
	 * Overwrite the `archived` property on each table with the per-user
	 * resolved value for $userId.
	 *
	 * Uses a single bulk DB query (chunked for Oracle compatibility) regardless
	 * of how many tables are in the array.
	 *
	 * @param Table[] $tables
	 * @return Table[]
	 * @throws Exception
	 */
	public function enrichTablesWithArchiveState(array $tables, string $userId): array {
		$nodeIds = array_map(fn (Table $t) => $t->getId(), $tables);
		$overrides = $this->userArchiveMapper->findAllOverridesForUser($userId, Application::NODE_TYPE_TABLE, $nodeIds);

		foreach ($tables as $table) {
			$override = $overrides[$table->getId()] ?? null;
			$archived = $override !== null ? $override->isArchived() : $table->isArchived();
			$table->setArchived($archived);
		}

		return $tables;
	}

	/**
	 * Overwrite the `archived` property on each context with the per-user
	 * resolved value for $userId.
	 *
	 * Uses a single bulk DB query (chunked for Oracle compatibility) regardless
	 * of how many contexts are in the array.
	 *
	 * @param Context[] $contexts
	 * @return Context[]
	 * @throws Exception
	 */
	public function enrichContextsWithArchiveState(array $contexts, string $userId): array {
		$nodeIds = array_map(fn (Context $c) => $c->getId(), $contexts);
		$overrides = $this->userArchiveMapper->findAllOverridesForUser($userId, Application::NODE_TYPE_CONTEXT, $nodeIds);

		foreach ($contexts as $context) {
			$override = $overrides[$context->getId()] ?? null;
			$archived = $override !== null ? $override->isArchived() : $context->isArchived();
			$context->setArchived($archived);
		}

		return $contexts;
	}

	/**
	 * Directly set the entity-level `archived` flag for a table or context row.
	 *
	 * Intentionally bypasses TableService / ContextService to avoid a circular
	 * dependency: those services will eventually call ArchiveService, so
	 * ArchiveService must not call them back for this low-level write.
	 *
	 * @throws Exception
	 * @throws InternalError
	 */
	private function setEntityArchived(int $nodeType, int $nodeId, bool $archived): void {
		$tableName = match ($nodeType) {
			Application::NODE_TYPE_TABLE => 'tables_tables',
			Application::NODE_TYPE_CONTEXT => 'tables_contexts_context',
			default => throw new InternalError('Unsupported node type for archiving: ' . $nodeType),
		};

		$qb = $this->connection->getQueryBuilder();
		$qb->update($tableName)
			->set('archived', $qb->createNamedParameter($archived, IQueryBuilder::PARAM_BOOL))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
