<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="main-view-view">
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
<style lang="scss">
.main-view-view {
	width: max-content;
	min-width: var(--app-content-width, 100%);
}
</style>
