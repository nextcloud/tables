<template>
	<div>
		<MainWrapper :element="activeView" :is-view="true" />
		<MainModals />
	</div>
</template>

<script>

import { mapGetters, mapState } from 'vuex'
import MainWrapper from '../modules/main/sections/MainWrapper.vue'
import MainModals from '../modules/main/modals/Modals.vue'

export default {
	name: 'MainViewWrapper',
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
				// view does not exists, go to startpage
				this.$router.push('/').catch(err => err)
			}
		},
	},
}
</script>
