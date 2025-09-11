/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { ColumnTypes } from './columnHandler.js'
import { ALLOWED_PROTOCOLS } from '../../../constants.ts'

export default {
	methods: {
		isValueValidForColumn(value, column) {
			const type = column?.type?.split('-')[0]
			const columnTypeDefault = type + 'Default'

			if (column.type === ColumnTypes.Selection) {
				if (
					(value instanceof Array && value.length > 0)
					|| (value === parseInt(value))
				) {
					return true
				}
				return columnTypeDefault in column && !(['', 'null'].includes(column[columnTypeDefault]))
			}
			let hasDefaultValue = columnTypeDefault in column && !(['', null].includes(column[columnTypeDefault]))
			if (column.type === ColumnTypes.SelectionMulti) {
				hasDefaultValue = columnTypeDefault in column && column[columnTypeDefault] !== '[]'
				return (value instanceof Array && value.length > 0) || hasDefaultValue
			}
			return (!!value || value === 0) || hasDefaultValue
		},

		checkMandatoryFields(row) {
			let mandatoryFieldsEmpty = false
			this.columns.forEach(col => {
				const isMandatory = col.viewColumnInformation?.mandatory ?? col.mandatory
				if (isMandatory) {
					const validValue = this.isValueValidForColumn(row[col.id], col)
					mandatoryFieldsEmpty = mandatoryFieldsEmpty || !validValue
				}
			})
			return mandatoryFieldsEmpty
		},

		isValidUrlProtocol(value) {
			if (!value) {
				return true
			}
			value = JSON.parse(value)
			try {
				const parsedUrl = new URL(value?.value)
				return ALLOWED_PROTOCOLS.includes(parsedUrl.protocol)
			} catch (e) {
				return false
			}
		},
	},
}
