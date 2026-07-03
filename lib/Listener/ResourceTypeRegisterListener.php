<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\OCM\Events\LocalOCMDiscoveryEvent;

/**
 * @template-implements IEventListener<LocalOCMDiscoveryEvent>
 */
class ResourceTypeRegisterListener implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof LocalOCMDiscoveryEvent) {
			return;
		}
		$event->registerResourceType(
			'tables',
			['user'],
			[
				'tables-v2' => '/ocs/v2.php/apps/tables/api/2/',
			]
		);
	}
}
