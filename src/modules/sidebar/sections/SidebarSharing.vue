<template>
	<div v-if="activeView" class="sharing">
		<div v-if="!activeView.isShared || activeView.ownership === getCurrentUser().uid">
			<ShareForm :shares="shares" @add="addShare" @update="updateShare" />
			<ShareList :shares="shares" @remove="removeShare" @update="updateShare" />
		</div>
		<div v-else style="margin-top: 12px;">
			{{ activeView ? t('tables', 'This table is shared with you. Resharing is not possible.') : t('tables', 'This view is shared with you. Resharing is not possible.') }}
		</div>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import shareAPI from '../mixins/shareAPI.js'
import ShareForm from '../partials/ShareForm.vue'
import ShareList from '../partials/ShareList.vue'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	components: {
		ShareForm,
		ShareList,
	},

	mixins: [shareAPI],

	data() {
		return {
			loading: false,

			// shared with
			shares: [],
		}
	},

	computed: {
		...mapGetters(['activeView']),
	},

	watch: {
		activeView() {
			if (this.activeView) {
				this.loadSharesFromBE()
			}
		},
	},

	mounted() {
		if (this.activeView) {
			this.loadSharesFromBE()
		}
	},

	methods: {
		getCurrentUser,
		async loadSharesFromBE() {
			this.loading = true
			this.shares = await this.getSharedWithFromBE()
			this.loading = false
		},
		async removeShare(shareId) {
			console.debug('remove share triggered', shareId)
			await this.removeShareFromBE(shareId)
			await this.loadSharesFromBE()
		},
		async addShare(share) {
			console.debug('add share triggered', share)
			await this.sendNewShareToBE(share)
			await this.loadSharesFromBE()
		},
		async updateShare(data) {
			console.debug('update share triggered', data)
			const shareId = data.id
			delete data.id
			await this.updateShareToBE(shareId, data)
			await this.loadSharesFromBE()
		},
	},
}
</script>
