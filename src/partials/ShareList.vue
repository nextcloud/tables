<template>
	<div>
		<h3>{{ t('tables', 'Existing shares') }}</h3>
		<div v-if="loading" class="icon-loading" />
		<ul v-if="shares && shares.length > 0" class="sharedWithList">
			<div v-for="share in shares"
				:key="share.id"
				class="row">
				<div class="fix-col-2">
					<Avatar :user="share.receiver" :display-name="share.receiver" />
					<div class="userDisplayName">
						{{ share.receiverDisplayName }}
					</div>
				</div>
				<div class="fix-col-2" style="justify-content: end;">
					<ShareInfoPopover :share="share" />

					<Actions :force-menu="true">
						<ActionCaption :title="t('tables', 'Permissions')" />
						<ActionCheckbox
							:checked.sync="share.permissionRead"
							@check="updatePermission(share.id, 'read', true)"
							@uncheck="updatePermission(share.id, 'read', false)">
							{{ t('tables', 'Can read data') }}
						</ActionCheckbox>
						<ActionCheckbox
							:checked.sync="share.permissionCreate"
							@check="updatePermission(share.id, 'create', true)"
							@uncheck="updatePermission(share.id, 'create', false)">
							{{ t('tables', 'Can create data') }}
						</ActionCheckbox>
						<ActionCheckbox
							:checked.sync="share.permissionUpdate"
							@check="updatePermission(share.id, 'update', true)"
							@uncheck="updatePermission(share.id, 'update', false)">
							{{ t('tables', 'Can update data') }}
						</ActionCheckbox>
						<ActionCheckbox
							:checked.sync="share.permissionDelete"
							@check="updatePermission(share.id, 'delete', true)"
							@uncheck="updatePermission(share.id, 'delete', false)">
							{{ t('tables', 'Can delete data') }}
						</ActionCheckbox>
						<ActionCheckbox
							:checked.sync="share.permissionManage"
							@check="updatePermission(share.id, 'manage', true)"
							@uncheck="updatePermission(share.id, 'manage', false)">
							{{ t('tables', 'Can manage table') }}
						</ActionCheckbox>
						<ActionSeparator />
						<ActionButton :close-after-click="true" icon="icon-delete" @click="actionDelete(share.id)">
							{{ t('tables', 'Delete') }}
						</ActionButton>
					</Actions>
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
// import UserBubble from '@nextcloud/vue/dist/Components/UserBubble'
import formatting from '../mixins/formatting'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import ShareInfoPopover from '../partials/ShareInfoPopover'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import ActionCaption from '@nextcloud/vue/dist/Components/ActionCaption'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'

export default {
	components: {
		// UserBubble,
		Avatar,
		ActionButton,
		Actions,
		ShareInfoPopover,
		ActionCheckbox,
		ActionCaption,
		ActionSeparator,
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
