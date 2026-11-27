<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Helper;

/**
 * The rule for links people open from link cells and menu items. Schemes compare
 * without case, as RFC 3986 defines them.
 */
final class UrlHelper {
	public const ALLOWED_SCHEMES = ['http', 'https'];

	public static function hasAllowedScheme(string $url): bool {
		$scheme = parse_url($url, PHP_URL_SCHEME);
		return is_string($scheme) && in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true);
	}

	/**
	 * A link with an allowed scheme and a host, without blanks or control characters.
	 * Hosts and paths may hold any other character, such as an internationalized domain.
	 */
	public static function isAllowedLink(string $url): bool {
		if (preg_match('/[\s\x00-\x1F\x7F]/u', $url) !== 0) {
			return false;
		}
		$host = parse_url($url, PHP_URL_HOST);
		return is_string($host) && $host !== '' && self::hasAllowedScheme($url);
	}
}
