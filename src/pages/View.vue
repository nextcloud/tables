<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="main-view-view">
		<div v-if="!activeView && errorMessage" class="error-container">
			<IconTables :size="64" style="margin-bottom: 1rem;" />
			<p>{{ errorMessage }}</p>
		</div>

		<div v-else-if="!activeView">
			<div class="icon-loading" />
		</div>

		<div v-else>
			<MainWrapper :element="activeView" :is-view="true" />
			<MainModals />
		</div>
	</div>
</template>

<script>
import { mapState, mapActions } from 'pinia'
import { useTablesStore } from '../store/store.js'
import MainWrapper from '../modules/main/sections/MainWrapper.vue'
import MainModals from '../modules/modals/Modals.vue'
import IconTables from '../shared/assets/icons/IconTables.vue'

export default {

	components: {
		MainWrapper,
		MainModals,
		IconTables,
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
				if (!this.activeViewId) {
					this.setActiveViewId(parseInt(id))
				}

				if (!this.activeView) {
					await this.loadContextView({ id })
				}
			} catch (e) {
				if (e.message === 'NOT_FOUND') {
					this.errorMessage = t('tables', 'This view could not be found')
				} else {
					this.errorMessage = t('tables', 'An error occurred while loading the view')
					console.error(e)
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

.error-container {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	text-align: center;
	padding: 2rem;
	height: 100dvh;
	min-height: 100%;
	color: var(--color-text);
	opacity: 0.6;

	p {
		font-size: clamp(1.2rem, 4vw, 2rem);
		font-weight: 600;
		max-width: 90%;
		word-wrap: break-word;
	}
}
}
</style>
