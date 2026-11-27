<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Model\MenuTarget;
use PHPUnit\Framework\TestCase;

class MenuTargetTest extends TestCase {
	public static function targets(): array {
		return [
			'a page' => [['pageId' => 5], ['pageId' => 5]],
			'a table or view' => [['nodeRelId' => 7], ['nodeRelId' => 7]],
			'a link, trimmed' => [['url' => " https://nextcloud.com\u{00A0}"], ['url' => 'https://nextcloud.com']],
		];
	}

	/**
	 * @dataProvider targets
	 */
	public function testATargetOpensOneThing(array $target, array $expected): void {
		$this->assertSame($expected, MenuTarget::fromArray($target)->toArray());
	}

	public static function malformedTargets(): array {
		return [
			'nothing' => [[], 'The target of a menu item'],
			'two targets' => [['pageId' => 5, 'nodeRelId' => 7], 'The target of a menu item'],
			'an extra key' => [['pageId' => 5, 'title' => 'Home'], 'The target of a menu item'],
			'an unknown key' => [['tableId' => 5], 'The target of a menu item'],
			'a page id as text' => [['pageId' => '5'], 'The target of a menu item'],
			'a link as a number' => [['url' => 5], 'The target of a menu item'],
			'a list' => [[5], 'The target of a menu item'],
			'a javascript link' => [['url' => 'javascript:alert(1)'], 'A link is an http or https address'],
			'a link that is too long' => [['url' => 'https://nextcloud.com/' . str_repeat('a', 2000)], 'A link is an http or https address'],
		];
	}

	/**
	 * @dataProvider malformedTargets
	 */
	public function testAMalformedTargetIsRefused(array $target, string $message): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage($message);
		MenuTarget::fromArray($target);
	}
}
