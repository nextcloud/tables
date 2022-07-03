<?php

namespace OCA\Tables\AppInfo;

use OCA\Tables\Listener\UserDeletedListener;
use OCP\AppFramework\App;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\User\Events\BeforeUserDeletedEvent;

class Application extends App {
	public const APP_ID = 'tables';

	public function __construct() {
		parent::__construct(self::APP_ID);

        /* @var IEventDispatcher $eventDispatcher */
        $dispatcher = $this->getContainer()->query(IEventDispatcher::class);
        $dispatcher->addServiceListener(BeforeUserDeletedEvent::class, UserDeletedListener::class);
	}
}
