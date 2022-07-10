<template>
	<div v-if="activeTable" class="sharing">
		<h1>{{ t('tables', 'Sharing') }}</h1>
		<div v-if="!activeTable.isShared">
			<ShareForm :shares="shares" @add="addShare" @update="updateShare" />
			<ShareList :shares="shares" @remove="removeShare" @update="updateShare" />
		</div>
		<div v-else>
			{{ t('tables', 'This table is shared with you. Resharing is not allowed.') }}
		</div>
	</div>
</template>

<script>
import { mapGetters, mapState } from 'vuex'
import shareAPI from '../../mixins/shareAPI'
import ShareForm from '../../partials/ShareForm'
import ShareList from '../../partials/ShareList'

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
		...mapState(['tables', 'tablesLoading', 'showSidebar']),
		...mapGetters(['activeTable']),
	},

	watch: {
		activeTable() {
			if (this.activeTable) {
				this.loadSharesFromBE()
			}
		},
	},

	mounted() {
		if (this.activeTable) {
			this.loadSharesFromBE()
		}
	},

	methods: {
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
