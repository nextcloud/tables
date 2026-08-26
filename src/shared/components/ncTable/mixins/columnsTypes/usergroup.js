/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractUsergroupColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'

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

}
