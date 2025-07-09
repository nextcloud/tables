/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError, showSuccess } from '@nextcloud/dialogs'

export default {
	methods: {
		async copyToClipboard(content, silent = true) {
			try {
				if (navigator?.clipboard) {
					await navigator.clipboard.writeText(content)
				} else {
					throw new Error('Clipboard is not available')
				}

				if (!silent) {
					showSuccess(t('tables', 'Copied to clipboard.'))
				}
				return true
			} catch (e) {
				console.error('Error copying to clipboard', e)
				if (!silent) {
					showError(t('tables', 'Clipboard is not available'))
				}
			}
		},
	},
}
