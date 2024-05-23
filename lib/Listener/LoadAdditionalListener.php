<?php

namespace OCA\Tables\Listener;

use OCA\Tables\AppInfo\Application;
use OCP\Collaboration\Resources\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/** @template-implements IEventListener<LoadAdditionalScriptsEvent> */
class LoadAdditionalListener implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof LoadAdditionalScriptsEvent)) {
			return;
		}

		if (method_exists(Util::class, 'addInitScript')) {
			Util::addInitScript(Application::APP_ID, 'tables-files');
		}
	}
}
