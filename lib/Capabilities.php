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

/**
 * Class Capabilities
 *
 * @package OCA\Tables
 */
class Capabilities implements ICapability {
	private IAppManager $appManager;

	public function __construct(IAppManager $appManager) {
		$this->appManager = $appManager;
	}

	/**
	 * @inheritDoc
	 */
	public function getCapabilities() {
		return [
			'tables' => [
				'enabled' => $this->appManager->isEnabledForUser('tables'),
				'version' => $this->appManager->getAppVersion('tables'),
				'apiVersions' => [
					'1.0'
				],
				'column_types' => [
					'text-line',
					'text-long`',
					'text-rich`',
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
				]
			],
		];
	}
}
