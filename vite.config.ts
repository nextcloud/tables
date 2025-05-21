// SPDX-FileCopyrightText: Ferdinand Thiessen <opensource@fthiessen.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

import { createAppConfig } from '@nextcloud/vite-config'
import path from 'path'

const config = createAppConfig({
	reference: path.join(__dirname, 'src', 'reference.js'),
	files: path.join(__dirname, 'src', 'file-actions.js'),
	main: path.join(__dirname, 'src', 'main.js'),
}, {
    inlineCSS: {
        jsAssetsFilterFunction: (chunk) => {
            return chunk.name === 'main' || 
                   chunk.fileName.includes('main') || 
                   chunk.fileName.includes('tables-main');
        }
    },
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
                external: [
                    'micromark-factory-destination',
                    'micromark-util-character',
                    'micromark-util-chunked',
                    'micromark-util-classify-character',
                    'micromark-util-resolve-all',
                    'micromark-util-subtokenize',
                    'micromark-util-decode-numeric-character-reference',
                    'micromark-util-decode-string',
					'micromark-util-html-tag-name',
					'micromark-factory-label',
					'micromark-factory-title',
					'micromark-util-normalize-identifier',
					'micromark-factory-whitespace',
                ],
			},
		},
	},
})

export default config
