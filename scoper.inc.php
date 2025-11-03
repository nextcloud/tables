<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Symfony\Component\Finder\Finder;

$depFinder = Finder::create()
	->files()
	->ignoreVCS(true)
	->notName('autoload.php');

// .prod-deps is generated through `composer install --no-dev` and `composer update --no-dev`
$productionDependencies = file('.scoper-production-dependencies', FILE_IGNORE_NEW_LINES);
$productionDependencies = array_map(function (string $file): string {
	return 'vendor/' . $file;
}, $productionDependencies);
$depFinder->in($productionDependencies);

return [
	'prefix' => 'OCA\\Tables\\Vendor',
	'output-dir' => 'lib/Vendor/',
	'finders' => [$depFinder],
];
