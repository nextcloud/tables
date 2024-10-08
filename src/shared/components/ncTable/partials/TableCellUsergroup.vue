<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<!-- eslint-disable-next-line vue/no-v-html -->
	<div v-if="value">
		<div v-for="item in value" :key="item.id" class="inline usergroup-entry">
			<NcUserBubble :user="item.id" :avatar-image="item.type === 1 ? 'icon-group' : ''" :is-no-user="item.type !== 0" :display-name="item.displayName ?? item.id" :show-user-status="isUser(item) && column.showUserStatus" :size="column.showUserStatus ? 34 : 20" :primary="isCurrentUser(item)" />
		</div>
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { NcUserBubble } from '@nextcloud/vue'

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
			return (item) => item.type === 0
		},
	},
}
</script>

<style lang="scss" scoped>
	.usergroup-entry {
		padding-right: 10px;
	}
</style>
