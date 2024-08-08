<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCA\Tables\AppInfo\Application;
use OCA\Text\Event\LoadEditor;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @template-implements IEventListener<Event|RenderReferenceEvent>
 */
class TablesReferenceListener implements IEventListener {
	private bool $isLoaded = false;
	public function __construct(
		private IEventDispatcher $eventDispatcher,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof RenderReferenceEvent) {
			return;
		}

		Util::addScript(Application::APP_ID, Application::APP_ID . '-reference');
		if (!$this->isLoaded && class_exists(LoadEditor::class)) {
			$this->isLoaded = true;
			$this->eventDispatcher->dispatchTyped(new LoadEditor());
		}
	}
}
