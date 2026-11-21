<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use OCA\Tables\Db\MenuItem;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Helper\TextHelper;
use OCA\Tables\Helper\UrlHelper;

/**
 * What a menu item opens: a page or a node relation of the application, or a link.
 * Whether the page or node belongs to the application is for the caller to check.
 */
final class MenuTarget {
	private const SHAPE = 'The target of a menu item is {pageId} of a page or {nodeRelId} of a table or view of this application, or {url} of a link.';

	private function __construct(
		public readonly ?int $pageId,
		public readonly ?int $nodeRelId,
		public readonly ?string $url,
	) {
	}

	/**
	 * @param array<array-key, mixed> $target exactly one of `pageId`, `nodeRelId` and `url`
	 * @throws BadRequestError
	 */
	public static function fromArray(array $target): self {
		if (count($target) !== 1) {
			throw new BadRequestError(self::SHAPE);
		}
		$value = reset($target);
		return match (true) {
			array_key_exists('pageId', $target) && is_int($value) => new self($value, null, null),
			array_key_exists('nodeRelId', $target) && is_int($value) => new self(null, $value, null),
			array_key_exists('url', $target) && is_string($value) => new self(null, null, self::link($value)),
			default => throw new BadRequestError(self::SHAPE),
		};
	}

	/**
	 * @return array{pageId: int}|array{nodeRelId: int}|array{url: string}
	 */
	public function toArray(): array {
		return match (true) {
			$this->pageId !== null => ['pageId' => $this->pageId],
			$this->nodeRelId !== null => ['nodeRelId' => $this->nodeRelId],
			default => ['url' => (string)$this->url],
		};
	}

	/**
	 * @throws BadRequestError
	 */
	private static function link(string $url): string {
		$url = TextHelper::trim($url);
		if (mb_strlen($url) > MenuItem::URL_MAX_LENGTH || !UrlHelper::isAllowedLink($url)) {
			throw new BadRequestError('A link is an http or https address of at most ' . MenuItem::URL_MAX_LENGTH . ' characters.');
		}
		return $url;
	}
}
