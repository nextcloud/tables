<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Constants;

enum FilterOperator: string {
	case BEGINS_WITH = 'begins-with';
	case ENDS_WITH = 'ends-with';
	case CONTAINS = 'contains';
	case DOES_NOT_CONTAIN = 'does-not-contain';
	case IS_EQUAL = 'is-equal';
	case IS_NOT_EQUAL = 'is-not-equal';
	case IS_GREATER_THAN = 'is-greater-than';
	case IS_GREATER_THAN_OR_EQUAL = 'is-greater-than-or-equal';
	case IS_LESS_THAN = 'is-lower-than';
	case IS_LESS_THAN_OR_EQUAL = 'is-lower-than-or-equal';
	case IS_EMPTY = 'is-empty';
}
