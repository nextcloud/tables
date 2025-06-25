<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<!-- eslint-disable-next-line vue/no-v-html -->
	<div v-if="value" class="table-cell-usergroup">
		<div v-for="item in value" :key="item.id" class="inline usergroup-entry">
			<NcUserBubble :user="item.id" :avatar-image="getAvatarImage(item)" :is-no-user="!isUser(item)" :display-name="item.displayName ?? item.id" :show-user-status="isUser(item) && column.showUserStatus" :size="column.showUserStatus ? 34 : 20" :primary="isCurrentUser(item)" />
		</div>
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { NcUserBubble } from '@nextcloud/vue'
import { USERGROUP_TYPE } from '../../../constants.ts'

const currentUser = getCurrentUser()

export default {
	name: 'TableCellUsergroup',
	components: {
		NcUserBubble,
	},
	props: {
		column: {
			type: Object,
			default: () => { },
		},
		rowId: {
			type: Number,
			default: null,
		},
		value: {
			type: Array,
			default: () => [],
		},
	},
	computed: {
		isCurrentUser() {
			return (item) => this.isUser(item) && item.id === currentUser?.uid
		},
		isUser() {
			return (item) => item.type === USERGROUP_TYPE.USER
		},
	},
	methods: {
		getAvatarImage(item) {
			if (item.type === USERGROUP_TYPE.GROUP) {
				return 'icon-group'
			}
			if (item.type === USERGROUP_TYPE.CIRCLE) {
				return 'icon-circles'
			}
			return ''
		},
	},
}
</script>

<style lang="scss" scoped>
	.table-cell-usergroup {
		display: flex;
		flex-wrap: wrap;
		padding: 10px;
	}

	.usergroup-entry {
		padding-right: 10px;
	}
</style>
