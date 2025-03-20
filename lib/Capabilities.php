<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables;

use OCA\Tables\Helper\CircleHelper;
use OCP\App\IAppManager;
use OCP\Capabilities\ICapability;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Class Capabilities
 *
 * @package OCA\Tables
 */
class Capabilities implements ICapability {
	private IAppManager $appManager;

	private LoggerInterface $logger;

	private IConfig $config;

	private CircleHelper $circleHelper;

	public function __construct(IAppManager $appManager, LoggerInterface $logger, IConfig $config, CircleHelper $circleHelper) {
		$this->appManager = $appManager;
		$this->logger = $logger;
		$this->config = $config;
		$this->circleHelper = $circleHelper;
	}

	/**
	 *
	 * @return array{tables: array{enabled: bool, version: string, apiVersions: list<string>, features: list<string>, isCirclesEnabled: bool, column_types: list<string>}}
	 *
	 * @inheritDoc
	 */
	public function getCapabilities(): array {
		$textColumnVariant = 'text-rich';
		if (version_compare($this->config->getSystemValueString('version', '0.0.0'), '26.0.0', '<')) {
			$textColumnVariant = 'text-long';
		}

		return [
			'tables' => [
				'enabled' => $this->appManager->isEnabledForUser('tables'),
				'version' => $this->appManager->getAppVersion('tables'),
				'apiVersions' => [
					'1.0',
					'2.0',
					'2.1',
				],
				'features' => [
					'favorite',
					'archive',
				],
				'isCirclesEnabled' => $this->circleHelper->isCirclesEnabled(),
				'column_types' => [
					'text-line',
					$textColumnVariant,
					'text-link',
					'number',
					'number-stars',
					'number-progress',
					'selection',
					'selection-multi',
					'selection-check',
					'datetime',
					'datetime-date',
					'datetime-time',
					'usergroup',
				]
			],
		];
	}
}
