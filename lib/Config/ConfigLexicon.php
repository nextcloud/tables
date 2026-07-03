<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Config;

use OCP\Config\Lexicon\Entry;
use OCP\Config\Lexicon\ILexicon;
use OCP\Config\Lexicon\Strictness;
use OCP\Config\ValueType;

/**
 * Config Lexicon for tables.
 *
 * Please Add & Manage your Config Keys in that file and keep the Lexicon up to date!
 *
 * {@see ILexicon}
 */
class ConfigLexicon implements ILexicon {
	public const FEDERATION_ENABLED = 'federationEnabled';

	#[\Override]
	public function getStrictness(): Strictness {
		return Strictness::IGNORE;
	}

	#[\Override]
	public function getAppConfigs(): array {
		return [
			new Entry(self::FEDERATION_ENABLED, ValueType::BOOL, true, 'Enable or disable federated table sharing'),
		];
	}

	#[\Override]
	public function getUserConfigs(): array {
		return [];
	}
}
