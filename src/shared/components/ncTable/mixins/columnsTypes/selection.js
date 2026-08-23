/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractSelectionColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'

export default class SelectionColumn extends AbstractSelectionColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.Selection
		this.selectionOptions = data.selectionOptions
	}

	getLabel(id) {
		const i = this.selectionOptions?.findIndex((obj) => obj.id === id)
		return this.selectionOptions[i]?.label
	}

	isDeletedLabel(value) {
		const i = this.selectionOptions?.findIndex((obj) => obj.id === value)
		return !!this.selectionOptions[i]?.deleted
	}

	parseValue(value) {
		return parseInt(value)
	}

}
