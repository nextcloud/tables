<template>
	<div>
		<MainWrapper :element="activeView" :is-view="true" />
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
		...mapState(['activeViewId']),
		...mapGetters(['activeView']),
	},

	watch: {
		activeViewId() {
			if (this.activeViewId && !this.activeView) {
				// view does not exist, go to startpage
				this.$router.push('/').catch(err => err)
			}
		},
	},
}
</script>
