<template>
	<MainWrapper :element="activeView" :is-view="true" />
</template>

<script>

import { mapGetters, mapState } from 'vuex'
import MainWrapper from './MainWrapper.vue'

export default {
	name: 'MainViewWrapper',
	components: {
		MainWrapper,
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
