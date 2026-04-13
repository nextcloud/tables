<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable;

/**
 * Thrown when the Airtable share page or schema endpoint cannot be reached or
 * its response cannot be parsed.  Covers network errors, unexpected HTTP status
 * codes, and HTML/JSON structure changes on Airtable's side.
 */
class AirtableFetchException extends \RuntimeException {
}
