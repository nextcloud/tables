<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use PHPUnit\Framework\TestCase;

if (!defined('PHPUNIT_RUN')) {
	define('PHPUNIT_RUN', 1);
}

$envMode = getenv('TEST_MODE');
if ($envMode !== 'local') {
	require_once __DIR__ . '/../../../../lib/base.php';
}
require_once __DIR__ . '/../../../../tests/autoload.php';

require_once __DIR__ . '/Database/DatabaseTestCase.php';

require_once __DIR__ . '/Db/Row2MapperTestDependencies.php';

if (!class_exists(TestCase::class)) {
	require_once('PHPUnit/Autoload.php');
}
