<?php

declare(strict_types = 1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use OCA\Tables\AppInfo\Application;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class FixContextsDefaults implements IRepairStep {

	public function __construct(
		protected IConfig $config,
		protected IDBConnection $dbc,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return 'Fix navigation bar default of existing contexts to show for all';
	}

	/**
	 * @inheritDoc
	 */
	public function run(IOutput $output) {
		$appVersion = $this->config->getAppValue(Application::APP_ID, 'installed_version', '0.0');
		if (\version_compare($appVersion, '0.8.0-beta.1', '>')) {
			$output->info('Not applicable, skipping.');
			return;
		}

		$qb = $this->dbc->getQueryBuilder();
		$qb->update('tables_contexts_navigation')
			->set('display_mode', $qb->createNamedParameter(Application::NAV_ENTRY_MODE_ALL, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('display_mode', $qb->createNamedParameter(Application::NAV_ENTRY_MODE_HIDDEN, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter('', IQueryBuilder::PARAM_STR)))
			->executeStatement();
	}
}
