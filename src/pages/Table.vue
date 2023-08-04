<template>
	<div>
		<MainWrapper :element="activeTable" :is-view="false" />
		<MainModals />
	</div>
</template>

<script>

import { mapGetters, mapState } from 'vuex'
import MainWrapper from '../modules/main/sections/MainWrapper.vue'
import MainModals from '../modules/main/modals/Modals.vue'

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
