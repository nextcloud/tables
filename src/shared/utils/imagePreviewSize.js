/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	IMAGE_PREVIEW_SIZE_DEFAULT,
	IMAGE_PREVIEW_SIZE_MAX,
	IMAGE_PREVIEW_SIZE_MIN,
} from '../constants.js'

export function isImagePreviewSizeValid(size) {
	const parsedSize = Number(size)
	return Number.isInteger(parsedSize)
		&& parsedSize >= IMAGE_PREVIEW_SIZE_MIN
		&& parsedSize <= IMAGE_PREVIEW_SIZE_MAX
}

export function normalizeImagePreviewSize(size) {
	if (size === '' || size === null || size === undefined) {
		return IMAGE_PREVIEW_SIZE_DEFAULT
	}

	if (!isImagePreviewSizeValid(size)) {
		const parsedSize = Number(size)
		if (Number.isInteger(parsedSize)) {
			return Math.min(Math.max(parsedSize, IMAGE_PREVIEW_SIZE_MIN), IMAGE_PREVIEW_SIZE_MAX)
		}
		return IMAGE_PREVIEW_SIZE_DEFAULT
	}

	return Number(size)
}
