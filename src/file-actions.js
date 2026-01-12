/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { FileAction, registerFileAction } from '@nextcloud/files'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
// eslint-disable-next-line import/no-unresolved
import tablesIcon from '@mdi/svg/svg/table-large.svg?raw'

import '@nextcloud/dialogs/style.css'

const validMimeTypes = [
	'text/csv',
	'text/html',
	'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	'application/vnd.ms-excel',
]

const fileAction = new FileAction({
	id: 'import-to-tables',
	displayName: () => t('tables', 'Import into Tables'),
	iconSvgInline: () => tablesIcon,

	enabled: (files) => {
		// Nextcloud v33 introduces `nodes` property
		const file = files[0] ?? files.nodes[0]

		return file.type === 'file' && validMimeTypes.includes(file.mime)
	},

	exec: async (file) => {
		// Nextcloud v33 introduces `nodes` property
		if (file.nodes) {
			file = file.nodes[0]
		}
		const { default: FileActionImport } = await import('./modules/modals/FileActionImport.vue')
		spawnDialog(FileActionImport, { file })
		return null
	},
})

registerFileAction(fileAction)
