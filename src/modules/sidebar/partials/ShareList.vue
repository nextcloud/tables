<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<h3>{{ t('tables', 'Shares') }}</h3>
		<div v-if="loading" class="icon-loading" />
		<ul v-if="getShares && getShares.length > 0" data-cy="sharedWithList" class="sharedWithList">
			<div v-for="share in getShares"
				:key="share.id"
				class="row">
				<div class="fix-col-2">
					<div style="display:flex; align-items: center;">
						<NcAvatar :user="share.receiver" :is-no-user="share.receiverType !== 'user'" />
					</div>
					<div class="userInfo">
						<div :class="{'high-line-height': !personHasTableManagePermission(share.receiver)}">
							{{ getShareDisplayName(share) }}
						</div>
						<div v-if="personHasTableManagePermission(share.receiver)">
							{{ '(' + t('tables', 'Table manager') + ')' }}
						</div>
					</div>
				</div>
				<div class="fix-col-2" style="justify-content: end;">
					<ShareInfoPopover v-if="debug" :share="share" />

					<NcActions :force-menu="true">
						<template v-if="personHasTableManagePermission(share.receiver)" #icon>
							<Crown :size="20" />
						</template>
						<template v-if="!personHasTableManagePermission(share.receiver)">
							<NcActionCaption :name="t('tables', 'Permissions')" />
							<NcActionCheckbox :checked.sync="share.permissionRead"
								:disabled="share.permissionManage || share.permissionUpdate || share.permissionDelete"
								@check="updatePermission(share, 'read', true)"
								@uncheck="updatePermission(share, 'read', false)">
								{{ t('tables', 'Read data') }}
							</NcActionCheckbox>
							<NcActionCheckbox :checked.sync="share.permissionCreate"
								:disabled="share.permissionManage"
								@check="updatePermission(share, 'create', true)"
								@uncheck="updatePermission(share, 'create', false)">
								{{ t('tables', 'Create data') }}
							</NcActionCheckbox>
							<NcActionCheckbox :checked.sync="share.permissionUpdate"
								:disabled="share.permissionManage"
								@check="updatePermission(share, 'update', true)"
								@uncheck="updatePermission(share, 'update', false)">
								{{ t('tables', 'Update data') }}
							</NcActionCheckbox>
							<NcActionCheckbox :checked.sync="share.permissionDelete"
								:disabled="share.permissionManage"
								@check="updatePermission(share, 'delete', true)"
								@uncheck="updatePermission(share, 'delete', false)">
								{{ t('tables', 'Delete data') }}
							</NcActionCheckbox>
							<NcActionCheckbox v-if="share.nodeType === 'view'"
								:checked.sync="share.permissionManage"
								@check="updatePermission(share, 'manage', true)"
								@uncheck="updatePermission(share, 'manage', false)">
								{{ t('tables', 'Manage view') }}
							</NcActionCheckbox>
							<NcActionButton v-if="!isView && !personHasTableManagePermission(share.receiver)"
								:close-after-click="true"
								@click="warnOnPromote(share)">
								<template #icon>
									<Crown :size="20" />
								</template>
								{{ t('tables', 'Promote to table manager') }}
							</NcActionButton>
							<NcActionSeparator />
							<NcActionButton :close-after-click="true" icon="icon-delete" @click="actionDelete(share)">
								{{ t('tables', 'Delete') }}
							</NcActionButton>
							<NcActionText>
								<template #icon>
									<Information :size="20" />
								</template>
								{{ t('tables', 'Last edit') + ': ' }}{{ updateTime(share) }}
							</NcActionText>
						</template>
						<template v-else-if="!isView">
							<NcActionButton
								:close-after-click="true"
								@click="demoteManager(share)">
								<template #icon>
									<Account :size="20" />
								</template>
								{{ t('tables', 'Demote to normal share') }}
							</NcActionButton>
							<NcActionText>
								<template #icon>
									<Information :size="20" />
								</template>
								{{ t('tables', 'Last edit') + ': ' }}{{ updateTime(share) }}
							</NcActionText>
						</template>
						<template v-else>
							<NcActionButton
								:close-after-click="true"
								@click="openDashboard()">
								<template #icon>
									<OpenInNew :size="20" />
								</template>
								{{ t('tables', 'Open main table to adjust table management permissions') }}
							</NcActionButton>
						</template>
					</NcActions>
				</div>
			</div>
		</ul>
		<div v-else>
			{{ t('tables', 'No shares') }}
		</div>
		<div>
			<DialogConfirmation :description="t('tables', 'After the promotion of the share recipient to table manager, any applications created by share recipients that utilise this table will continue to access its data, even if you later demote them.')"
				:title="t('tables', 'Confirm table manager promotion')"
				:cancel-title="t('tables', 'Cancel')"
				:confirm-title="t('tables', 'Promote to table manager')"
				confirm-class="warning"
				:show-modal="showModal"
				@confirm="promoteToManager"
				@cancel="showModal=false" />
		</div>
	</div>
</template>

<script>
import { mapState } from 'pinia'
import { useTablesStore } from '../../../store/store.js'
import formatting from '../../../shared/mixins/formatting.js'
import { NcActions, NcActionButton, NcAvatar, NcActionCheckbox, NcActionCaption, NcActionSeparator, NcActionText } from '@nextcloud/vue'
import ShareInfoPopover from './ShareInfoPopover.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import Crown from 'vue-material-design-icons/Crown.vue'
import Information from 'vue-material-design-icons/InformationOutline.vue'
import Account from 'vue-material-design-icons/AccountOutline.vue'
import moment from '@nextcloud/moment'
import { showWarning } from '@nextcloud/dialogs'
import DialogConfirmation from '../../../shared/modals/DialogConfirmation.vue'
import '@nextcloud/dialogs/style.css'

export default {
	components: {
		NcAvatar,
		NcActionButton,
		NcActions,
		Information,
		Account,
		NcActionText,
		ShareInfoPopover,
		NcActionCheckbox,
		NcActionCaption,
		NcActionSeparator,
		OpenInNew,
		Crown,
		DialogConfirmation,
	},

	mixins: [formatting],

	props: {
		shares: {
			type: Array,
			default: () => ([]),
		},
	},

	data() {
		return {
			loading: false,
			// To enable the share info popup
			debug: false,
			showModal: false,
			currentShare: {},
		}
	},

	computed: {
		...mapState(useTablesStore, ['tables', 'showSidebar', 'isLoadingSomething', 'activeElement', 'isView']),
		sortedShares() {
			return [...this.userShares, ...this.groupShares].slice()
				.sort(this.sortByDisplayName)
		},
		getShares() {
			if (this.isView) {
				return this.viewShares
			} else {
				return this.tableShares
			}
		},
		viewShares() {
			return this.shares.filter(share => share.nodeType === 'view')
		},
		tableShares() {
			return this.shares.filter(share => share.nodeType === 'table')
		},
	},

	methods: {
		updateTime(share) {
			return (share && share.lastEditAt) ? this.relativeDateTime(share.lastEditAt) : ''
		},
		relativeDateTime(v) {
			return moment(v).format('L') === moment().format('L') ? t('tables', 'Today') + ' ' + moment(v).format('LT') : moment(v).format('LLLL')
		},
		async openDashboard() {
			await this.$router.push('/table/' + this.activeElement.tableId)
		},
		actionDelete(share) {
			this.$emit('remove', share)
		},
		updatePermission(share, permission, value) {
			this.$emit('update', { id: share.id, permission, value })
		},
		warnOnPromote(share) {
			this.currentShare = share
			this.showModal = true
		},
		promoteToManager() {
			if (!this.currentShare) return
			this.$emit('update', { id: this.currentShare?.id, permission: 'manage', value: true })
			this.currentShare = {}
			this.showModal = false
		},
		async demoteManager(share) {
			showWarning(t('tables', 'Any application created by a demoted share recipients using a shared table will continue to consume its data.', { share: share.displayName }))
			this.$emit('update', { id: share.id, permission: 'manage', value: false })
		},
		personHasTableManagePermission(userId) {
			return this.tableShares.find(share => share.receiver === userId)?.permissionManage
		},
		getShareDisplayName(share) {
			const name = share.receiverDisplayName || share.receiver
			const type = share.receiverType

			if (type === 'group') {
				return `${name} (${t('tables', 'group')})`
			} else if (type === 'circle') {
				return `${name} (${t('tables', 'team')})`
			}
			return name
		},
	},
}
</script>

<style lang="scss" scoped>

	.sharedWithList li {
		display: flex;
		justify-content: space-between;
		line-height: 44px;
	}

	.userInfo {
		padding-inline-start: 5px;
		font-size: 100%;
		display: flex;
		flex-direction: column;
	}

	.high-line-height {
		line-height: 35px;
	}

	.manage-button {
		display: flex;
		justify-content: center;
	}

</style>
