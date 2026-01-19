/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import moment from '@nextcloud/moment'
import Papa from 'papaparse'
import generalHelper from '../../../mixins/generalHelper.js'
import { ColumnTypes } from './columnHandler.js'
import {
	TYPE_META_ID, TYPE_META_CREATED_BY, TYPE_META_CREATED_AT, TYPE_META_UPDATED_BY, TYPE_META_UPDATED_AT,
} from '../../../../shared/constants.ts'
import { useDataStore } from '../../../../store/data.js'

export default {

	mixins: [generalHelper],

	methods: {

		getRelationLookupValue(column, row, dataStore) {
			const relationColumnId = column.customSettings?.relationColumnId
			if (!relationColumnId) {
				return ''
			}

			const relationCell = row.data?.find(d => d.columnId === relationColumnId)
			if (!relationCell) {
				return ''
			}

			try {
				const relationLookup = dataStore.getRelations(column.id)
				if (!relationLookup?.values || !relationCell.value) {
					return ''
				}

				const relatedRow = relationLookup.values[relationCell.value]
				if (!relatedRow || relatedRow.value === null || relatedRow.value === undefined) {
					return ''
				}

				try {
					const valueString = relationLookup.column.getValueString({ value: relatedRow.value })
					return valueString || ''
				} catch (error) {
					console.warn('Failed to get value string for relation lookup:', error)
					return ''
				}
			} catch (error) {
				console.warn('Failed to get relation lookup value for export:', error)
				return ''
			}
		},

		downloadCsv(rows, columns, fileName) {
			if (!rows || rows.length === 0) {
				console.debug('downloadCSV has empty parameter, expected array ob row objects', rows)
			}

			const dataStore = useDataStore()
			const data = []
			rows.forEach(row => {
				const rowData = { ID: row.id }
				columns.forEach(column => {
					// if a normal column
					if (column.id >= 0) {
						if (column.type === ColumnTypes.RelationLookup) {
							rowData[column.title] = this.getRelationLookupValue(column, row, dataStore)
						} else {
							const set = row.data ? row.data.find(d => d.columnId === column.id) || '' : null
							rowData[column.title] = set ? column.getValueString(set) : ''
						}
					} else {
						// if is a meta data column (id < 0)
						switch (column.id) {
						case TYPE_META_ID:
							rowData[column.title] = row.id
							break
						case TYPE_META_CREATED_BY:
							rowData[column.title] = row.createdBy
							break
						case TYPE_META_UPDATED_BY:
							rowData[column.title] = row.lastEditBy
							break
						case TYPE_META_CREATED_AT:
							rowData[column.title] = row.createdAt
							break
						case TYPE_META_UPDATED_AT:
							rowData[column.title] = row.lastEditAt
							break
						}
					}
				})
				data.push(rowData)
			})

			const csv = Papa.unparse(data, {
				escapeFormulae: true,
			})

			// remove smileys from title
			const tableTitle = fileName.replace(/([#0-9]\u20E3)|[\xA9\xAE\u203C\u2047-\u2049\u2122\u2139\u3030\u303D\u3297\u3299][\uFE00-\uFEFF]?|[\u2190-\u21FF][\uFE00-\uFEFF]?|[\u2300-\u23FF][\uFE00-\uFEFF]?|[\u2460-\u24FF][\uFE00-\uFEFF]?|[\u25A0-\u25FF][\uFE00-\uFEFF]?|[\u2600-\u27BF][\uFE00-\uFEFF]?|[\u2900-\u297F][\uFE00-\uFEFF]?|[\u2B00-\u2BF0][\uFE00-\uFEFF]?|(?:\uD83C[\uDC00-\uDFFF]|\uD83D[\uDC00-\uDEFF])[\uFE00-\uFEFF]?/g, '')
			this.downloadFile(csv, moment().format('YY-MM-DD_HH-mm') + '_' + tableTitle + '.csv')
		},

		downloadFile(content, fileName) {
			const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' })
			const url = URL.createObjectURL(blob)
			const link = document.createElement('a')
			link.href = url
			link.setAttribute('download', fileName)
			document.body.appendChild(link)
			link.click()
			document.body.removeChild(link)
			URL.revokeObjectURL(url)
		},
	},
}
