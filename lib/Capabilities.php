<?php
/**
 * @copyright Copyright (c) 2023 Florian Steffens <florian.steffens@nextcloud.com>
 *
 * @author Florian Steffens <florian.steffens@nextcloud.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Tables;

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

	public function __construct(IAppManager $appManager, LoggerInterface $logger, IConfig $config) {
		$this->appManager = $appManager;
		$this->logger = $logger;
		$this->config = $config;
	}

	/**
	 *
	 * @return array{tables: array{enabled: bool, version: string, apiVersions: string[], features: string[], column_types: string[]}}
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
					'1.0'
				],
				'features' => [
					'favorite',
					'archive',
				],
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
