<?php

namespace OCA\Tables\Helper;

use InvalidArgumentException;
use OCA\Tables\AppInfo\Application;

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
			'table' => Application::NODE_TYPE_TABLE,
			'view' => Application::NODE_TYPE_VIEW,
			default => throw new InvalidArgumentException('Invalid node type'),
		};
	}
}
