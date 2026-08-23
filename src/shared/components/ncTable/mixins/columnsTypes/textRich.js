/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractTextColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'

export default class TextRichColumn extends AbstractTextColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.TextRich
	}

}
