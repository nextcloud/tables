<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable;

/**
 * Thrown when the Airtable share URL points to a base that requires
 * authentication (login wall detected in the response).  The user must supply
 * the __Host-airtable-session cookie to import this base.
 */
class AirtableBaseNotPublicException extends \RuntimeException {
}
