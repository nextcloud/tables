<template>
	<div class="sharing">
		<h1>{{ t('tables', 'Sharing') }}</h1>
		<div v-if="!activeTable.isShared">
			<ShareForm :shares="shares" @add="addShare" @update="updateShare" />
			<ShareList :shares="shares" />
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
			this.loadSharesFromBE()
		},
	},

	mounted() {
		this.loadSharesFromBE()
	},

	methods: {
		async loadSharesFromBE() {
			this.loading = true
			this.shares = await this.getSharedWithFromBE()
			this.loading = false
		},
		removeShare(share) {
			console.debug('remove share triggered', share)
		},

		addShare(share) {
			console.debug('add share triggered', share)
		},
		updateShare(share) {
			console.debug('update share triggered', share)
		},
	},
}
</script>
