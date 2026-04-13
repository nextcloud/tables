<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

/**
 * Handles Airtable field type: multipleAttachments (skip-and-report)
 *
 * File attachments will be fully imported in Phase 1 once the
 * files column type and AttachmentConverter are implemented (B1.8).
 */
class AttachmentSkipConverter extends AbstractSkipConverter {

	public function getAirtableType(): string {
		return 'multipleAttachments';
	}

	protected function getSkipReason(array $rawAirtableColumn): string {
		return 'File attachment fields will be supported in Phase 1. '
			. 'Re-import with Phase 1 enabled to include attachments.';
	}
}
