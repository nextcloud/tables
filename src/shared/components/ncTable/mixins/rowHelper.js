/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { ColumnTypes } from './columnHandler.js'
import { ALLOWED_PROTOCOLS } from '../../../constants.ts'
import Moment from '@nextcloud/moment'

export default {
	methods: {
		isMandatory(column) {
			const viewInfo = column?.viewColumnInformation ?? {}
			return viewInfo.mandatory ?? column?.mandatory ?? false
		},
		isValueValidForColumn(value, column) {
			switch (column.type) {
			case ColumnTypes.Datetime:
			case ColumnTypes.DatetimeDate:
			case ColumnTypes.DatetimeTime:
				return this.isDatetimeValueValid(value, column)
			case ColumnTypes.Selection:
				return this.isSelectionValueValid(value, column)
			case ColumnTypes.MultiSelection:
				return this.isMultiSelectionValueValid(value, column)
			case ColumnTypes.Usergroup:
				return this.isUsergroupValueValid(value, column)
			default:
				return this.isStandardValueValid(value, column)
			}
		},
		getColumnTypeDefault(column) {
			const type = column?.type?.split('-')[0]
			return type + 'Default'
		},
		isDatetimeValueValid(value, column) {
			 const columnTypeDefault = this.getColumnTypeDefault(column)

			if (!value || value === 'none') {
				return !this.isMandatory(column) || (!!column[columnTypeDefault] && column[columnTypeDefault] !== 'none')
			}
			if (column.type === ColumnTypes.DatetimeTime) {
				return Moment(value, 'HH:mm', true).isValid()
			}
			return !isNaN(Date.parse(value))
		},
		isSelectionValueValid(value, column) {
			 const columnTypeDefault = this.getColumnTypeDefault(column)

			if ((value instanceof Array && value.length > 0) || (value === parseInt(value))) {
				return true
			}
			const hasDefaultValue = columnTypeDefault in column && !(['', 'null'].includes(column[columnTypeDefault]))
			return hasDefaultValue
		},
		isMultiSelectionValueValid(value, column) {
			const columnTypeDefault = this.getColumnTypeDefault(column)

			const hasDefaultValue = columnTypeDefault in column && column[columnTypeDefault] !== '[]'
			return (value instanceof Array && value.length > 0) || hasDefaultValue
		},
		isUsergroupValueValid(value, column) {
			const columnTypeDefault = this.getColumnTypeDefault(column)

			// Handle array values
			if (value instanceof Array) {
				return value.length > 0
			}

			// Handle string values (JSON from backend)
			if (typeof value === 'string') {
				// Empty string or empty array string
				if (value === '' || value === '[]') {
					const hasDefaultValue = columnTypeDefault in column && column[columnTypeDefault] !== '[]' && column[columnTypeDefault] !== ''
					return hasDefaultValue
				}
				// Try to parse as JSON array
				try {
					const parsed = JSON.parse(value)
					if (parsed instanceof Array) {
						return parsed.length > 0
					}
				} catch (e) {
					// Not valid JSON, fall through to default check
				}
			}

			// Handle null/undefined and any other edge cases
			const hasDefaultValue = columnTypeDefault in column && column[columnTypeDefault] !== '[]' && column[columnTypeDefault] !== ''
			return hasDefaultValue
		},
		isStandardValueValid(value, column) {
			const columnTypeDefault = this.getColumnTypeDefault(column)

			const hasDefaultValue = columnTypeDefault in column && !(['', null].includes(column[columnTypeDefault]))
			return (!!value || value === 0) || hasDefaultValue
		},

		checkMandatoryFields(row) {
			let mandatoryFieldsEmpty = false
			if (!this.columns) return false
			this.columns.forEach(col => {
				if (this.isMandatory(col)) {
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
