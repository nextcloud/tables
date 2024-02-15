<template>
	<div class="main-table-view">
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
}
</script>
<style lang="scss">
.main-table-view {
	width: max-content;
	min-width: var(--app-content-width, 100%);
}
</style>
