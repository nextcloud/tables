import { showError } from '@nextcloud/dialogs'

/**
 * @param {Error} e error object
 * @param {string} message Error print message
 */
function handleAxiosError(e, message) {
	console.error('Axios error', e)
	const status = e.response?.status || null
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
 * Print error to console and show toast
 *
 * @param {Error} e The error
 * @param {string} msg Message to print out
 */
export default function(e, msg) {
	handleAxiosError(e, msg)
}
