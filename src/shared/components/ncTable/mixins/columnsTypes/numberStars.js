/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractNumberColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'

export default class NumberStarsColumn extends AbstractNumberColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.NumberStars
	}

}
