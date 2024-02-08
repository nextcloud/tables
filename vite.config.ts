// SPDX-FileCopyrightText: Ferdinand Thiessen <opensource@fthiessen.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

import { createAppConfig } from '@nextcloud/vite-config'
import path from 'path'

const config = createAppConfig({
	reference: path.join(__dirname, 'src', 'reference.js'),
	files: path.join(__dirname, 'src', 'file-actions.js'),
	main: path.join(__dirname, 'src', 'main.js'),
}, {
	inlineCSS: true,
	config: {
		build: {
			cssCodeSplit: false,
			rollupOptions: {
				output: {
					manualChunks: (id) => {
						if (id.includes('img/material/')) {
							return 'material-icons'
						}
					},
				},
			},
		},
	},
})

export default config
