<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Constants\ShareReceiverType;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class ResetPublicSharePermissions implements IRepairStep {

	public function __construct(
		protected IConfig $config,
		protected IDBConnection $dbc,
	) {
	}

	public function getName(): string {
		return 'Reset public link share permissions to read-only for versions before 2.0.2';
	}

	public function run(IOutput $output): void {
		$appVersion = $this->config->getAppValue(Application::APP_ID, 'installed_version', '0.0');
		if (\version_compare($appVersion, '2.0.2', '>=')) {
			$output->info('Not applicable, skipping.');
			return;
		}

		$qb = $this->dbc->getQueryBuilder();
		$qb->update('tables_shares')
			->set('permission_read', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			->set('permission_create', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->set('permission_update', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->set('permission_delete', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->set('permission_manage', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('receiver_type', $qb->createNamedParameter(ShareReceiverType::LINK, IQueryBuilder::PARAM_STR)))
			->executeStatement();

		$output->info('Reset public link share permissions to read-only.');
	}
}
