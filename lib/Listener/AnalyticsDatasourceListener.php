<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Listener;

use OCA\Analytics\Datasource\DatasourceEvent;
use OCA\Tables\Analytics\AnalyticsDatasource;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event|DatasourceEvent>
 */
class AnalyticsDatasourceListener implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof DatasourceEvent)) {
			// Unrelated
			return;
		}
		$event->registerDatasource(AnalyticsDatasource::class);
	}
}
