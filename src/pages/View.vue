<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="main-view-view">
		<div v-if="!activeView && !errorMessage">
			<div class="icon-loading" />
		</div>

		<div v-else-if="activeView">
			<MainWrapper :element="activeView" :is-view="true" />
		</div>

		<ErrorMessage v-else-if="errorMessage" :message="errorMessage" />

		<MainModals />
	</div>
</template>

<script>
import { mapState, mapActions } from 'pinia'
import { useTablesStore } from '../store/store.js'
import MainWrapper from '../modules/main/sections/MainWrapper.vue'
import MainModals from '../modules/modals/Modals.vue'
import ErrorMessage from '../modules/main/partials/ErrorMessage.vue'
import displayError, { getNotFoundError, getGenericLoadError } from '../shared/utils/displayError.js'

export default {

	components: {
		MainWrapper,
		MainModals,
		ErrorMessage,
	},

	data() {
		return {
			errorMessage: null,
		}
	},

	computed: {
		...mapState(useTablesStore, ['activeViewId', 'activeView']),
	},

	watch: {
		'$route.params.viewId': {
			immediate: true,
			handler() {
				this.errorMessage = null
				this.checkView()
			},
		},
	},

	methods: {
		...mapActions(useTablesStore, ['setActiveViewId', 'loadContextView']),

		async checkView() {
			const id = this.activeViewId || this.$route.params.viewId
			if (!id) return

			try {
				await this.loadContextView({ id })
				this.setActiveViewId(parseInt(id))
			} catch (e) {
				if (e.message === 'NOT_FOUND') {
					this.errorMessage = getNotFoundError('view')
				} else {
					this.errorMessage = getGenericLoadError('view')
					displayError(e, this.errorMessage)
				}
			}
		},
	},
}
</script>
<style lang="scss" scoped>
.main-view-view {
	width: max-content;
	min-width: var(--app-content-width, 100%);
}

:deep(h1) {
	font-size: unset;
	font-size: revert;
}
</style>
