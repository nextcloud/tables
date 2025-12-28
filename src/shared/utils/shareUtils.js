/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Checks if a share is a public link share
 * @param {object} share
 * @return {boolean}
 */
export const isPublicLinkShare = (share) => {
	return share.receiverType === 'link'
}
