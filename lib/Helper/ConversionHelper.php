<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Helper;

use InvalidArgumentException;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\View;

class ConversionHelper {

	/**
	 * @throws InvalidArgumentException
	 */
	public static function constNodeType2String(int $nodeType): string {
		return match ($nodeType) {
			Application::NODE_TYPE_TABLE => 'table',
			Application::NODE_TYPE_VIEW => 'view',
			default => throw new InvalidArgumentException('Invalid node type'),
		};
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public static function stringNodeType2Const(string $nodeType): int {
		return match ($nodeType) {
			'table', 'tables' => Application::NODE_TYPE_TABLE,
			'view', 'views' => Application::NODE_TYPE_VIEW,
			default => throw new InvalidArgumentException('Invalid node type'),
		};
	}

	public static function object2String(Table|View $node): string {
		if ($node instanceof Table) {
			return 'table';
		}
		return 'view';
	}
}
