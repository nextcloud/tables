/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { showError } from '@nextcloud/dialogs'

export function getNotFoundError(type) {
	return t('tables', 'This {type} could not be found', { type })
}

export function getGenericLoadError(type) {
	return t('tables', 'An error occurred while loading the {type}', { type })
}

/**
 * @param {Error} e error object
 * @param {string} message Error print message
 * @param {number} status http code
 */
function handleAxiosError(e, message, status) {
	console.error('Axios error', e)
	showError(message + ' ' + statusMessage(e, status))
}

/**
 * @param {Error} e error object
 * @param {number} status http code
 * @return {string}
 */
function statusMessage(e, status) {
	if (status === 400) {
		if (e.response?.data?.ocs?.data?.message) {
			return e.response.data.ocs.data.message
		}
		// for some reason the "edit" row returns a different structure
		if (e.response?.data?.message) {
			return e.response.data.message
		}
		return t('tables', 'Unknown error.')
	}
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
