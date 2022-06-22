<template>
	<div>
		<h3>{{ t('tables', 'Existing shares') }}</h3>
		<div v-if="loading" class="icon-loading" />
		<ul v-if="shares" class="sharedWithList">
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
						<ActionButton :close-after-click="true" icon="icon-delete">
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

export default {
	components: {
		// UserBubble,
		Avatar,
		ActionButton,
		Actions,
		ShareInfoPopover,
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
