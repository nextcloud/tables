import { FileAction, registerFileAction } from '@nextcloud/files'
import { spawnDialog } from '@nextcloud/dialogs'
// eslint-disable-next-line import/no-unresolved
import tablesIcon from '@mdi/svg/svg/table-large.svg?raw'

import '@nextcloud/dialogs/style.css'

__webpack_nonce__ = btoa(OC.requestToken) // eslint-disable-line
__webpack_public_path__ = OC.linkTo('tables', 'js/') // eslint-disable-line

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
		const file = files[0]

		return file.type === 'file' && validMimeTypes.includes(file.mime)
	},

	exec: async (file) => {
		const { default: FileActionImport } = await import('./modules/modals/FileActionImport.vue')
		spawnDialog(FileActionImport, { file })
		return null
	},
})

registerFileAction(fileAction)
