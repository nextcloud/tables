<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCP\App\IAppManager;
use OCP\Server;
use PHPUnit\Framework\TestCase;

if (!defined('PHPUNIT_RUN')) {
	define('PHPUNIT_RUN', 1);
}

require_once __DIR__ . '/../../../../lib/base.php';
require_once __DIR__ . '/../../../../tests/autoload.php';

Server::get(IAppManager::class)->loadApp('tables');

if (!class_exists(TestCase::class)) {
	require_once('PHPUnit/Autoload.php');
}

OC_Hook::clear();
