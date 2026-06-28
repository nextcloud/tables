<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Nextcloud\Rector\Set\NextcloudSets;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
	->withPaths([
		__DIR__ . '/appinfo',
		__DIR__ . '/lib',
		__DIR__ . '/tests',
	])
	->withSkip([
		__DIR__ . '/lib/Vendor',
		__DIR__ . '/tests/integration/vendor',
	])
	->withPhpSets(php81: true)
	->withTypeCoverageLevel(0)
	->withSets([
		NextcloudSets::NEXTCLOUD_33,
	]);
