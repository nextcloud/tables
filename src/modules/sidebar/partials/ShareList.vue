<template>
	<div>
		<h3>{{ t('tables', 'Shares') }}</h3>
		<div v-if="loading" class="icon-loading" />
		<ul v-if="shares && shares.length > 0" class="sharedWithList">
			<div v-for="share in shares"
				:key="share.id"
				class="row">
				<div class="fix-col-2">
					<NcAvatar :user="share.receiver" :display-name="share.receiver" />
					<div class="userDisplayName">
						{{ share.receiverDisplayName }}
					</div>
				</div>
				<div class="fix-col-2" style="justify-content: end;">
					<ShareInfoPopover :share="share" />

					<NcActions :force-menu="true">
						<NcActionCaption :title="t('tables', 'Permissions')" />
						<NcActionCheckbox :checked.sync="share.permissionRead"
							:disabled="share.permissionManage"
							@check="updatePermission(share.id, 'read', true)"
							@uncheck="updatePermission(share.id, 'read', false)">
							{{ t('tables', 'Read data') }}
						</NcActionCheckbox>
						<NcActionCheckbox :checked.sync="share.permissionCreate"
							:disabled="share.permissionManage"
							@check="updatePermission(share.id, 'create', true)"
							@uncheck="updatePermission(share.id, 'create', false)">
							{{ t('tables', 'Create data') }}
						</NcActionCheckbox>
						<NcActionCheckbox :checked.sync="share.permissionUpdate"
							:disabled="share.permissionManage"
							@check="updatePermission(share.id, 'update', true)"
							@uncheck="updatePermission(share.id, 'update', false)">
							{{ t('tables', 'Update data') }}
						</NcActionCheckbox>
						<NcActionCheckbox :checked.sync="share.permissionDelete"
							:disabled="share.permissionManage"
							@check="updatePermission(share.id, 'delete', true)"
							@uncheck="updatePermission(share.id, 'delete', false)">
							{{ t('tables', 'Delete data') }}
						</NcActionCheckbox>
						<NcActionCheckbox :checked.sync="share.permissionManage"
							@check="updatePermission(share.id, 'manage', true)"
							@uncheck="updatePermission(share.id, 'manage', false)">
							{{ t('tables', 'Manage table') }}
						</NcActionCheckbox>
						<NcActionSeparator />
						<NcActionButton :close-after-click="true" icon="icon-delete" @click="actionDelete(share.id)">
							{{ t('tables', 'Delete') }}
						</NcActionButton>
					</NcActions>
				</div>
			</div>
		</ul>
		<div v-else>
			{{ t('tables', 'No shares') }}
		</div>
	</div>
</template>

<script>
import { mapGetters, mapState } from 'vuex'
import formatting from '../../../shared/mixins/formatting.js'
import { NcActions, NcActionButton, NcAvatar, NcActionCheckbox, NcActionCaption, NcActionSeparator } from '@nextcloud/vue'
import ShareInfoPopover from './ShareInfoPopover.vue'

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
		...mapGetters(['activeTable']),
		sortedShares() {
			return [...this.userShares, ...this.groupShares].slice()
				.sort(this.sortByDisplayName)
		},
	},

	methods: {
		sortByDisplayName(a, b) {
			if (a.displayName.toLowerCase() < b.displayName.toLowerCase()) return -1
			if (a.displayName.toLowerCase() > b.displayName.toLowerCase()) return 1
			return 0
		},
		actionDelete(shareId) {
			this.$emit('remove', shareId)
		},
		updatePermission(id, permission, value) {
			this.$emit('update', { id, permission, value })
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
