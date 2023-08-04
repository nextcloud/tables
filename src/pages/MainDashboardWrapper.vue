<template>
	<MainWrapper :element="activeTable" :is-view="false" />
</template>

<script>

import { mapGetters, mapState } from 'vuex'
import MainWrapper from './MainWrapper.vue'

export default {
	name: 'MainDashboardWrapper',
	components: {
		MainWrapper,
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
	},
}
</script>
