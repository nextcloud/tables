<?php

declare(strict_types=1);

namespace OCA\Tables\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\Job;
use OCP\IDBConnection;
use OCP\IUserManager;

class ConvertViewColumnsFormat extends Job {
	public function __construct(
		ITimeFactory $time,
		protected IDBConnection $connection,
		protected IUserManager $userManager,
	) {
		parent::__construct($time);
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

		// Check for new views added during processing
		$qb = $this->connection->getQueryBuilder();
		$qb->select('id', 'columns')
			->from('tables_views')
			->where($qb->expr()->gt('id', $qb->createNamedParameter($maxId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('columns'));

		$result = $qb->executeQuery();

		// Process new views if any
		while ($view = $result->fetch()) {
			$this->processView($view, $updateQb);
		}

		$result->closeCursor();
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

		if (is_array($columns[0])) {
			return;
		}

		// Create new columns structure
		$newColumns = [];
		foreach ($columns as $order => $columnId) {
			$newColumns[] = [
				'columnId' => (int)$columnId,
				'order' => $order,
			];
		}

		// Execute update query
		$updateQb->setParameter('columns', json_encode($newColumns))
			->setParameter('id', $view['id'], \PDO::PARAM_INT)
			->executeStatement();
	}
} 