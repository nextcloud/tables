<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Constants;

enum ViewLayout: string {
	case TABLE = 'table';
	case TILES = 'tiles';
	case GALLERY = 'gallery';

	/**
	 * The layout a value names, or null when this version defines none by that name.
	 *
	 * Layouts reach us from a scheme, a federated instance and a stored row alike, any of
	 * which may name one only a newer version knows, so an unknown value is dropped rather
	 * than rejected.
	 */
	public static function tryFromMixed(mixed $layout): ?self {
		return is_string($layout) ? self::tryFrom($layout) : null;
	}

	/**
	 * The layout a value names, falling back to the one a view renders with by default.
	 */
	public static function normalize(mixed $layout): self {
		return self::tryFromMixed($layout) ?? self::TABLE;
	}
}
