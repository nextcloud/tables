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
			Application::NODE_TYPE_CONTEXT => 'context',
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

	/**
	 * Map a node type as stored in `tables_shares`, which unlike the node
	 * types above also covers contexts.
	 *
	 * Returns null for anything unknown so callers can skip it. Kept separate
	 * from stringNodeType2Const() on purpose: that method doubles as a
	 * validation gate for endpoints that only handle tables and views.
	 */
	public static function shareNodeType2Const(string $nodeType): ?int {
		return match ($nodeType) {
			'table', 'tables' => Application::NODE_TYPE_TABLE,
			'view', 'views' => Application::NODE_TYPE_VIEW,
			'context', 'contexts' => Application::NODE_TYPE_CONTEXT,
			default => null,
		};
	}

	public static function object2String(Table|View $node): string {
		if ($node instanceof Table) {
			return 'table';
		}
		return 'view';
	}
}
