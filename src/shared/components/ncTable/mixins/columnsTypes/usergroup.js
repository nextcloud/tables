/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractUsergroupColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'

export default class UsergroupColumn extends AbstractUsergroupColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.Usergroup
		this.showUserStatus = data.showUserStatus
		this.usergroupMultipleItems = data.usergroupMultipleItems
		this.usergroupSelectUsers = data.usergroupSelectUsers
		this.usergroupSelectGroups = data.usergroupSelectGroups
		this.usergroupSelectTeams = data.usergroupSelectTeams
	}

	getValueString(valueObject) {
		valueObject = valueObject || this.value || null

		const valueObjects = this.getObjects(valueObject.value)
		let ret = ''
		valueObjects?.forEach(obj => {
			if (ret === '') {
				ret = obj.id
			} else {
				ret += ', ' + obj.id
			}
		})
		return ret
	}

	getObjects(values) {
		const objects = []
		values?.forEach(item => {
			objects.push(item)
		})
		return objects
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(this.getValueString(cell), cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = (filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value).toLowerCase()
		const valueString = this.getValueString(cell).toLowerCase()

		const filterMethod = {
			[FilterIds.Contains]() { return valueString?.includes(filterValue) },
			[FilterIds.DoesNotContain]() { return !valueString?.includes(filterValue) },
			[FilterIds.IsEqual]() { return valueString === filterValue },
			[FilterIds.IsNotEqual]() { return valueString !== filterValue },
			[FilterIds.IsEmpty]() { return !valueString },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

}
