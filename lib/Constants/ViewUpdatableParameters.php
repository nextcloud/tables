<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Constants;

enum ViewUpdatableParameters: string {
	case TITLE = 'title';
	case EMOJI = 'emoji';
	case DESCRIPTION = 'description';
	case SORT = 'sort';
	case FILTER = 'filter';
	case COLUMN_SETTINGS = 'columns';
}
