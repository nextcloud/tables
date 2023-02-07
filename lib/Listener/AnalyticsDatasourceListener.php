<?php
/**
 * Analytics data source
 * Report Table App data with the Analytics App
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 */
declare(strict_types=1);

namespace OCA\Tables\Listener;

use OCA\Tables\Datasource\AnalyticsDatasource;
use OCA\Analytics\Datasource\DatasourceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class AnalyticsDatasourceListener implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof DatasourceEvent)) {
			// Unrelated
			return;
		}
		$event->registerDatasource(AnalyticsDatasource::class);
	}
}
