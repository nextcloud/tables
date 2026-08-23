/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractDatetimeColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import Moment from '@nextcloud/moment'

export default class DatetimeTimeColumn extends AbstractDatetimeColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.DatetimeTime
	}

	formatValue(value) {
		return Moment(value, 'HH:mm:ss').format('LT')
	}

}
