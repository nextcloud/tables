/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractNumberColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'

export default class NumberColumn extends AbstractNumberColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.Number
		this.numberDecimals = data.numberDecimals
		this.numberMax = data.numberMax
		this.numberMin = data.numberMin
		this.numberPrefix = data.numberPrefix
		this.numberSuffix = data.numberSuffix
	}

	parseValue(value) {
		return value === null ? null : parseFloat(value)
	}

}
