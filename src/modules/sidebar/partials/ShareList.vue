<template>
	<div>
		<h3>{{ t('tables', 'Shares') }}</h3>
		<div v-if="loading" class="icon-loading" />
		<ul v-if="viewShares && viewShares.length > 0" class="sharedWithList">
			<div v-for="share in viewShares"
				:key="share.id"
				class="row">
				<div class="fix-col-2">
					<NcAvatar :user="share.receiver" :is-no-user="share.receiverType !== 'user'" />
					<div class="userDisplayName">
						{{ share.receiverDisplayName }}{{ share.receiverType === 'group' ? ' (' + t('tables', 'group') + ')' : '' }}
					</div>
				</div>
				<div class="fix-col-2" style="justify-content: end;">
					<ShareInfoPopover :share="share" />

					<NcActions :force-menu="true">
						<NcActionCaption :title="t('tables', 'Permissions')" />
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
						<NcActionCheckbox :checked.sync="share.permissionManage"
							@check="updatePermission(share, 'manage', true)"
							@uncheck="updatePermission(share, 'manage', false)">
							{{ t('tables', 'Manage view') }}
						</NcActionCheckbox>
						<NcActionButton v-if="activeView.isBaseView && !personHasTablePermission(share.receiver)"
							:close-after-click="true"
							@click="addTablePermission(share)">
							<template #icon>
								<AccountTie :size="20" />
							</template>
							{{ t('tables', 'Add Manage table') }}
						</NcActionButton>
						<NcActionButton v-if="!activeView.isBaseView" @click="openBaseView()">
							<template #icon>
								<OpenInNew :size="20" />
							</template>
							{{ t('tables', 'To manage table manage rights, open the base table') }}
						</NcActionButton>
						<NcActionSeparator />
						<NcActionButton :close-after-click="true" icon="icon-delete" @click="actionDelete(share)">
							{{ t('tables', 'Delete') }}
						</NcActionButton>
					</NcActions>
				</div>
			</div>
		</ul>
		<div v-else>
			{{ t('tables', 'No shares') }}
		</div>
		<h3 v-if="tableShares && tableShares.length > 0">
			{{ t('tables', 'Table managers') }}
		</h3>
		<ul v-if="tableShares && tableShares.length > 0" class="sharedWithList">
			<div v-for="share in tableShares"
				:key="share.id"
				class="row">
				<div class="fix-col-2">
					<NcAvatar :user="share.receiver" :is-no-user="share.receiverType !== 'user'" />
					<div class="userDisplayName">
						{{ share.receiverDisplayName }}{{ share.receiverType === 'group' ? ' (' + t('tables', 'group') + ')' : '' }}
					</div>
				</div>
				<div class="fix-col-2" style="justify-content: end;">
					<ShareInfoPopover :share="share" />

					<NcActions v-if="activeView.isBaseView">
						<NcActionButton :close-after-click="true" icon="icon-delete" @click="actionDelete(share)">
							{{ t('tables', 'Delete') }}
						</NcActionButton>
					</NcActions>
				</div>
			</div>
		</ul>
	</div>
</template>

<script>
import { mapGetters, mapState } from 'vuex'
import formatting from '../../../shared/mixins/formatting.js'
import { NcActions, NcActionButton, NcAvatar, NcActionCheckbox, NcActionCaption, NcActionSeparator } from '@nextcloud/vue'
import ShareInfoPopover from './ShareInfoPopover.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import AccountTie from 'vue-material-design-icons/AccountTie.vue'

export default {
	components: {
		// UserBubble,
		NcAvatar,
		NcActionButton,
		NcActions,
		ShareInfoPopover,
		NcActionCheckbox,
		NcActionCaption,
		NcActionSeparator,
		OpenInNew,
		AccountTie,
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
		}
	},

	computed: {
		...mapState(['tables', 'tablesLoading', 'showSidebar']),
		...mapGetters(['activeView', 'getBaseView']),
		sortedShares() {
			return [...this.userShares, ...this.groupShares].slice()
				.sort(this.sortByDisplayName)
		},
		viewShares() {
			return this.shares.filter(share => share.nodeType === 'view').filter(share => this.tableShares.find(tableShare => tableShare.nodeId === this.activeView.tableId && share.receiver === tableShare.receiver) === undefined)
		},
		tableShares() {
			return this.shares.filter(share => share.nodeType === 'table')
		},
	},

	methods: {
		async openBaseView() {
			await this.$router.push('/view/' + this.getBaseView(this.activeView.tableId).id)
		},
		sortByDisplayName(a, b) {
			if (a.displayName.toLowerCase() < b.displayName.toLowerCase()) return -1
			if (a.displayName.toLowerCase() > b.displayName.toLowerCase()) return 1
			return 0
		},
		actionDelete(share) {
			this.$emit('remove', share)
		},
		updatePermission(share, permission, value) {
			this.$emit('update', { id: share.id, permission, value })
		},
		addTablePermission(share) {
			this.$emit('add-table-share', share)
		},
		personHasTablePermission(userId) {
			return this.tableShares.find(share => share.receiver === userId) !== undefined
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

	.userDisplayName {
		padding-left: 5px;
		font-size: 100%;
		line-height: 35px;
	}

</style>
