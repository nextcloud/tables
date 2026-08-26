/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractSelectionColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'

export default class SelectionMutliColumn extends AbstractSelectionColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.SelectionMulti
		this.selectionOptions = data.selectionOptions
	}

	default() {
		if (!this.selectionDefault) {
			return []
		}
		return JSON.parse(this.selectionDefault)
	}

	getDefaultObjects() {
		return this.getObjects(this.default())
	}

	getObjects(values) {
		// values is an array of option-ids as string
		const objects = []
		values?.forEach(id => {
			const optionsObject = this.getOptionObject(parseInt(id))
			// skip options that not exists anymore
			if (optionsObject) {
				objects.push(optionsObject)
			}
		})
		return objects
	}

	getOptionObject(id) {
		const i = this.selectionOptions?.findIndex(obj => {
			return obj.id === id
		})
		if (i !== undefined) {
			return this.selectionOptions[i] || null
		}
	}

}
