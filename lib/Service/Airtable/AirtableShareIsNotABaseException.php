<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable;

/**
 * Thrown when the supplied Airtable URL is a view-share or embed-share rather
 * than a full base-share.  Only base-share URLs (those whose first path segment
 * starts with "shr") can be imported.
 */
class AirtableShareIsNotABaseException extends \RuntimeException {
}
