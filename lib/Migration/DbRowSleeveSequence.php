<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Doctrine\DBAL\Schema\Sequence;
use OCA\Tables\AppInfo\Application;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

class DbRowSleeveSequence implements IRepairStep {
	public function __construct(
		protected IDBConnection $db,
		protected IConfig $config,
		protected LoggerInterface $logger,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return 'Fixing the sequence of the row-sleeves table';
	}

	/**
	 * @inheritDoc
	 */
	public function run(IOutput $output) {
		$legacyRowTransferRunComplete = $this->config->getAppValue('tables', 'legacyRowTransferRunComplete', 'false') === 'true';
		$sequenceRepairComplete = $this->config->getAppValue('tables', 'sequenceRepairComplete', 'false') === 'true';
		if (!$legacyRowTransferRunComplete || $sequenceRepairComplete) {
			return;
		}

		$platform = $this->db->getDatabasePlatform();
		if (!$platform->supportsSequences()) {
			$this->config->setAppValue('tables', 'sequenceRepairComplete', 'true');
			return;
		}

		$newSequenceOffset = $this->getNewOffset();
		if ($newSequenceOffset === null) {
			// no data, no op
			$this->config->setAppValue('tables', 'sequenceRepairComplete', 'true');
			return;
		}

		$schema = $this->db->createSchema();
		$sequences = $schema->getSequences();

		$candidates = array_filter($sequences, function (string $sequenceName): bool {
			return str_contains($sequenceName, 'tables_row_sleeves');
		}, ARRAY_FILTER_USE_KEY);

		if (count($candidates) > 1) {
			$this->logger->error('Unexpected number of sequences, aborting.', [
				'app' => Application::APP_ID,
				'sequences' => $candidates,
			]);
			throw new \LogicException('Failed to find the correct sequence.');
		} elseif (count($candidates) === 0) {
			// 🤷
			$this->config->setAppValue('tables', 'sequenceRepairComplete', 'true');
			return;
		}
		/** @var Sequence $sequence */
		$sequence = $candidates[array_key_first($candidates)];

		$this->db->executeStatement(sprintf('ALTER SEQUENCE %s RESTART START WITH %d', $sequence->getName(), $newSequenceOffset));
		$this->config->setAppValue('tables', 'sequenceRepairComplete', 'true');
	}

	protected function getNewOffset(): ?int {
		$maxIdFromSleeves = $this->getMaxIdFromTable('tables_row_sleeves');
		$maxIdFromLegacy = $this->getMaxIdFromTable('tables_rows');

		if ($maxIdFromSleeves === null && $maxIdFromLegacy === null) {
			return null;
		}

		return 1 + max($maxIdFromSleeves ?? -1, $maxIdFromLegacy ?? -1);
	}

	protected function getMaxIdFromTable(string $tableName): ?int {
		$maxQuerySleeves = $this->db->getQueryBuilder();
		$result = $maxQuerySleeves->select($maxQuerySleeves->createFunction('MAX(' . $maxQuerySleeves->getColumnName('id') . ')'))
			->from($tableName)
			->executeQuery();

		$row = $result->fetch();
		$result->closeCursor();

		return $row ? (int)$row[array_key_first($row)] : null;
	}
}
