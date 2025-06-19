<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\BackgroundJob;

use OCA\Tables\Service\ValueObject\ViewColumnInformation;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Server;

class ConvertViewColumnsFormat extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		protected IDBConnection $connection,
		protected IUserManager $userManager,
	) {
		parent::__construct($time);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	public function run($argument) {
		$qb = $this->connection->getQueryBuilder();

		// Get all views that need processing
		$qb->select('id', 'columns')
			->from('tables_views')
			->where($qb->expr()->isNotNull('columns'));

		$result = $qb->executeQuery();

		// Predefine update query
		$updateQb = $this->connection->getQueryBuilder();
		$updateQb->update('tables_views')
			->set('columns', $updateQb->createParameter('columns'))
			->where($updateQb->expr()->eq('id', $updateQb->createParameter('id')));

		$maxId = 0;
		// Update each view
		while ($view = $result->fetch()) {
			$this->processView($view, $updateQb);
			$maxId = max($maxId, (int)$view['id']);
		}

		$result->closeCursor();

		$jobList = Server::get(IJobList::class);
		$jobList->removeById($this->id);
	}

	/**
	 * Process a single view and update its columns format
	 *
	 * @param array $view The view data containing id and columns
	 * @param \OCP\DB\QueryBuilder\IQueryBuilder $updateQb The update query builder
	 */
	private function processView(array $view, \OCP\DB\QueryBuilder\IQueryBuilder $updateQb): void {
		if (empty($view['columns'])) {
			return;
		}

		// Parse existing columns JSON
		$columns = json_decode($view['columns'], true);
		if (!is_array($columns)) {
			return;
		}

		if (isset($columns[0]) && is_array($columns[0])) {
			return;
		}

		// Create new columns structure
		$newColumns = [];
		foreach ($columns as $order => $columnId) {
			$newColumns[] = new ViewColumnInformation((int)$columnId, order: $order);
		}

		// Execute update query
		$updateQb->setParameter('columns', json_encode($newColumns))
			->setParameter('id', $view['id'], \PDO::PARAM_INT)
			->executeStatement();
	}
}
