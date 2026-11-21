<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Helper;

use OCA\Tables\Helper\UrlHelper;
use PHPUnit\Framework\TestCase;

class UrlHelperTest extends TestCase {
	public static function links(): array {
		return [
			'https' => ['https://nextcloud.com', true],
			'http with a path and query' => ['http://example.com/a/b?c=d#e', true],
			'upper case scheme' => ['HTTPS://nextcloud.com', true],
			'internationalized domain' => ['https://www.müller.de/', true],
			'non-ASCII path' => ['https://example.com/straße/日本', true],
			'underscore in the host' => ['https://my_host.example/', true],
			'javascript' => ['javascript:alert(1)', false],
			'ftp' => ['ftp://example.com/file', false],
			'no host' => ['https:///path', false],
			'no scheme' => ['example.com', false],
			'a space' => ['https://exa mple.com', false],
			'a control character' => ["https://example.com/\x07", false],
		];
	}

	/**
	 * @dataProvider links
	 */
	public function testALinkNeedsAnAllowedSchemeAndAHost(string $url, bool $allowed): void {
		$this->assertSame($allowed, UrlHelper::isAllowedLink($url));
	}

	public function testTheSchemeComparesWithoutCase(): void {
		$this->assertTrue(UrlHelper::hasAllowedScheme('HTTPS://nextcloud.com'));
		$this->assertTrue(UrlHelper::hasAllowedScheme('Http://nextcloud.com'));
		$this->assertFalse(UrlHelper::hasAllowedScheme('javascript:https://nextcloud.com'));
	}
}
