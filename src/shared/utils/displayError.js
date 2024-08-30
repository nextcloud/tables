/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { showError } from '@nextcloud/dialogs'

/**
 * @param {Error} e error object
 * @param {string} message Error print message
 * @param {number} status http code
 */
function handleAxiosError(e, message, status) {
	console.error('Axios error', e)
	showError(message + ' ' + statusMessage(status))
}

/**
 * @param {number} status http code
 * @return {string}
 */
function statusMessage(status) {
	if (status === 401) {
		return t('tables', 'Request is not authorized. Are you logged in?')
	} else if (status === 403) {
		return t('tables', 'Request not allowed.')
	} else if (status === 404) {
		return t('tables', 'Resource not found.')
	} else {
		return t('tables', 'Unknown error.')
	}
}

/**
 * Handle errors that only have a msg
 *
 * @param {Error} e The error
 * @param {string} msg as message to toast out
 */
function displaySimpleError(e = null, msg = '') {
	console.error('Error occurred: ' + (msg ?? ''), e?.message)
	showError(msg)
}

/**
 * Print error to console and show toast
 *
 * @param {Error} e The error
 * @param {string} msg Message to print out
 */
export default function(e, msg) {
	const status = e?.response?.status || null

	if (status) {
		handleAxiosError(e, msg, status)
	} else {
		displaySimpleError(e, msg)
	}
}
