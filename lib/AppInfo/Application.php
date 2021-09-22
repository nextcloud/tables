<?php

namespace OCA\Tables\AppInfo;

use OCP\AppFramework\App;

class Application extends App {
	public const APP_ID = 'tables';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}
}
