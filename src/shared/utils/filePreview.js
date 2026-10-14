/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateUrl } from '@nextcloud/router'

// The query parameters a link may carry the file id in, compared lowercased: the server
// writes fileId, while the Files app and older links write fileid or openfile.
const FILE_ID_PARAMETERS = ['fileid', 'openfile']

/**
 * Whether the given value can be used as a file id.
 *
 * @param {unknown} value a candidate id, as a string or a number
 * @return {boolean} true when it is a positive whole number
 */
export function isFileId(value) {
	if (typeof value === 'number') {
		return Number.isInteger(value) && value > 0
	}

	return typeof value === 'string' && /^\d+$/.test(value)
}

/**
 * The id of a file on this server that the given link names, or null.
 *
 * A link is user supplied, so a foreign host is never followed: the preview would be
 * requested by everyone who sees the row. Only the id is taken out of the link and the
 * request is rebuilt from it, so no attacker chosen path or query string is ever sent.
 *
 * @param {unknown} value a link, absolute or relative to this server
 * @return {string|null} the file id, or null when the link names no local file
 */
export function findLocalFileId(value) {
	if (typeof value !== 'string' || value === '') {
		return null
	}

	let url
	try {
		// A cell may hold the link with JSON escaped slashes.
		url = new URL(value.replace(/\\\//g, '/'), window.location.origin)
	} catch (error) {
		return null
	}

	if (url.origin !== window.location.origin) {
		return null
	}

	for (const [name, parameter] of url.searchParams) {
		if (FILE_ID_PARAMETERS.includes(name.toLowerCase()) && isFileId(parameter)) {
			return parameter
		}
	}

	// The id may be followed by more path, as in /f/1/preview.
	return url.pathname.match(/\/f\/(\d+)(?:\/|$)/)?.[1] ?? null
}

/**
 * A preview request for the given file, at the given edge length.
 *
 * @param {string|number} fileId the file to render
 * @param {number} size the edge length in pixels
 * @return {string} the preview URL
 */
export function buildPreviewUrl(fileId, size) {
	const parameters = new URLSearchParams({
		fileId: String(fileId),
		x: String(size),
		y: String(size),
		a: '1',
	})

	return generateUrl('/core/preview') + '?' + parameters.toString()
}
