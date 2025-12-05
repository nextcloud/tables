<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Constants;

enum ColumnType: string {
	case NUMBER = 'number';
	case TEXT = 'text';
	case SELECTION = 'selection';
	case DATETIME = 'datetime';
	case PEOPLE = 'usergroup';
}
