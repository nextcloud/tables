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
			const type = column?.type?.split('-')[0]
			const columnTypeDefault = type + 'Default'
			let hasDefaultValue

			// Datetime types ('none' counts as empty)
			if ([
				ColumnTypes.Datetime,
				ColumnTypes.DatetimeDate,
			].includes(column.type)) {
				return !value || value === 'none'
					? !this.isMandatory(column) || (!!column[columnTypeDefault] && column[columnTypeDefault] !== 'none')
					: !isNaN(Date.parse(value))
			}

			if (column.type === ColumnTypes.DatetimeTime) {
				return !value || value === 'none'
					? !this.isMandatory(column) || (!!column[columnTypeDefault] && column[columnTypeDefault] !== 'none')
					: Moment(value, 'HH:mm', true).isValid()
			}

			// Single selection types (value must be non-empty or default exists)
			if (column.type === ColumnTypes.Selection) {
				if (
					(value instanceof Array && value.length > 0)
					|| (value === parseInt(value))
				) {
					return true
				}
				hasDefaultValue = columnTypeDefault in column && !(['', 'null'].includes(column[columnTypeDefault]))
				return hasDefaultValue
			}

			// Multi selection types (array must have items or default exists)
			if (column.type === ColumnTypes.SelectionMulti) {
				hasDefaultValue = columnTypeDefault in column && column[columnTypeDefault] !== '[]'
				return (value instanceof Array && value.length > 0) || hasDefaultValue
			}
			// Standard check for other types (non-empty value or default exists)
			hasDefaultValue = columnTypeDefault in column && !(['', null].includes(column[columnTypeDefault]))
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
