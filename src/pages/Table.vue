<template>
	<div>
		<MainWrapper :element="activeTable" :is-view="false" />
		<MainModals />
	</div>
</template>

<script>

import { mapGetters, mapState } from 'vuex'
import MainWrapper from '../modules/main/sections/MainWrapper.vue'
import MainModals from '../modules/modals/Modals.vue'

export default {
	components: {
		MainWrapper,
		MainModals,
	},

	computed: {
		...mapState(['activeTableId']),
		...mapGetters(['activeTable']),
	},

	watch: {
		activeTableId() {
			if (this.activeTableId && !this.activeTable) {
				// table does not exists, go to startpage
				this.$router.push('/').catch(err => err)
			}
		},
		activeTable() {
			if (this.activeTableId && !this.activeTable) {
				// table does not exists, go to startpage
				this.$router.push('/').catch(err => err)
			}
		},
	},

	created() {
		this.$store.commit('setColumns', { tableId: this.activeTableId, columns: [] })
		this.$store.commit('setRows', { tableId: this.activeTableId, rows: [] })
	},

	unmounted() {
		this.$store.commit('removeColumns', this.activeTableId)
		this.$store.commit('removeRows', this.activeTableId)
		this.$store.commit('removeLoading', this.activeTableId)
	},
}
</script>
