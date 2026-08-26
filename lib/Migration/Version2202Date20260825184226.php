<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use OCA\Tables\Vendor\Symfony\Component\Uid\Uuid;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * FIXME Auto-generated migration step: Please modify to your needs!
 */
class Version2202Date20260825184226 extends SimpleMigrationStep {

	private const TARGET_TABLE_COLUMNS = 'tables_columns';
	private const COL_ID = 'id';
	private const COL_SELECTION_OPTIONS = 'selection_options';

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qbColOptionsUuidUpdate = $this->db->getQueryBuilder();
		$qbColOptionsUuidUpdate->update(self::TARGET_TABLE_COLUMNS)
			->set(self::COL_SELECTION_OPTIONS, $qbColOptionsUuidUpdate->createParameter('columnSelectionOptions'))
			->where($qbColOptionsUuidUpdate->expr()->eq(self::COL_ID, $qbColOptionsUuidUpdate->createParameter('columnLocalId')));

		$qbSelect = $this->db->getQueryBuilder();
		$qbSelect->select(self::COL_ID, self::COL_SELECTION_OPTIONS)
			->from(self::TARGET_TABLE_COLUMNS)
			->where($qbSelect->expr()->like(self::COL_SELECTION_OPTIONS, $qbSelect->createNamedParameter('[{"id":%')))
			->andWhere($qbSelect->expr()->notLike(self::COL_SELECTION_OPTIONS, $qbSelect->createNamedParameter('%,"uuid":"%')));
		$select = $qbSelect->executeQuery();

		$writeBatches = 250;
		$updates = 0;

		try {
			$this->db->beginTransaction();
			while (($columnData = $select->fetchAssociative()) !== false) {
				$columnId = $columnData[self::COL_ID];
				$this->applyColumnOptionsUpdateIfNecessary($qbColOptionsUuidUpdate, (int)$columnId, $columnData[self::COL_SELECTION_OPTIONS]);

				$updates++;
				if ($updates % $writeBatches === 0) {
					$this->db->commit();
					$this->db->beginTransaction();
				}
			}
			$this->db->commit();
		} catch (\Exception $e) {
			$this->db->rollBack();
			throw $e;
		}

		$select->closeCursor();
	}

	private function applyColumnOptionsUpdateIfNecessary(IQueryBuilder $query, int $columnId, ?string $rawSelectionOptions): void {
		$columnSelectionOptions = trim($rawSelectionOptions ?? '');
		if ($columnSelectionOptions === '') {
			return;
		}

		$selectionOptions = \json_decode($columnSelectionOptions, true);
		if (!is_array($selectionOptions) || empty($selectionOptions)) {
			return;
		}

		foreach ($selectionOptions as &$selectionOption) {
			if (!isset($selectionOption['uuid'])) {
				$selectionOption['uuid'] = Uuid::v7()->toRfc4122();
			}

		}
		unset($selectionOption);

		$updatedSelectionOptions = json_encode($selectionOptions);
		$query->setParameters(
			[
				'columnLocalId' => $columnId,
				'columnSelectionOptions' => $updatedSelectionOptions,
			],
			[
				Types::INTEGER,
				Types::TEXT,
			]
		);
		$query->executeStatement();
	}
}
