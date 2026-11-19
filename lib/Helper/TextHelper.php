<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Helper;

use OCA\Tables\Errors\BadRequestError;

/**
 * The rule for the optional titles and descriptions of pages, widgets and menu items.
 */
final class TextHelper {
	public const TITLE_MAX_LENGTH = 200;
	public const DESCRIPTION_MAX_LENGTH = 2000;

	/** White space, Unicode spaces such as the no-break space, and zero width spaces. */
	private const EDGE_BLANKS = '/^[\s\p{Z}\x{200B}\x{FEFF}]+|[\s\p{Z}\x{200B}\x{FEFF}]+$/u';

	/**
	 * The text without blanks at either end, so a text of blanks only is empty.
	 */
	public static function trim(string $text): string {
		return preg_replace(self::EDGE_BLANKS, '', $text) ?? trim($text);
	}

	/**
	 * An optional text of at most `$maxLength` characters after {@see self::trim()}, or
	 * null when nothing is left.
	 *
	 * @throws \InvalidArgumentException when it is not a text or is too long
	 */
	public static function optional(mixed $text, int $maxLength): ?string {
		if ($text === null) {
			return null;
		}
		if (!is_string($text)) {
			throw new \InvalidArgumentException('not a text');
		}
		$text = self::trim($text);
		if (mb_strlen($text) > $maxLength) {
			throw new \InvalidArgumentException('longer than ' . $maxLength . ' characters');
		}
		return $text === '' ? null : $text;
	}

	/**
	 * An optional title, see {@see self::optional()}.
	 *
	 * @param string $of what the title belongs to, such as "a menu item" or "widget 2"
	 * @throws BadRequestError
	 */
	public static function title(mixed $title, string $of): ?string {
		return self::optionalOrRefuse($title, self::TITLE_MAX_LENGTH, 'The title of ' . $of);
	}

	/**
	 * An optional description, see {@see self::optional()}.
	 *
	 * @param string $of what the description belongs to, such as "a menu item" or "widget 2"
	 * @throws BadRequestError
	 */
	public static function description(mixed $description, string $of): ?string {
		return self::optionalOrRefuse($description, self::DESCRIPTION_MAX_LENGTH, 'The description of ' . $of);
	}

	/**
	 * @throws BadRequestError
	 */
	private static function optionalOrRefuse(mixed $text, int $maxLength, string $subject): ?string {
		try {
			return self::optional($text, $maxLength);
		} catch (\InvalidArgumentException) {
			throw new BadRequestError($subject . ' must be a text of at most ' . $maxLength . ' characters.');
		}
	}
}
