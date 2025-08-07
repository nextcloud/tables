<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="main-table-view">
		<div v-if="errorMessage" class="error-container">
			<IconTables :size="64" style="margin-bottom: 1rem;" />
			<p>{{ errorMessage }}</p>
		</div>

		<div v-else-if="!activeTable">
			<div class="icon-loading" />
		</div>

		<div v-else>
			<MainWrapper :element="activeTable" :is-view="false" />
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
		...mapState(useTablesStore, ['activeTableId', 'activeTable']),
	},

	watch: {
		'$route.params.tableId': {
			immediate: true,
			handler() {
				this.errorMessage = null // reset previous error
				this.checkTable()
			},
		},
	},

	methods: {
		...mapActions(useTablesStore, ['loadContextTable', 'setActiveTableId']),

		async checkTable() {
			const id = this.activeTableId || this.$route.params.tableId
			if (!id) return

			try {
				if (!this.activeTableId) {
					this.setActiveTableId(parseInt(id))
				}

				if (!this.activeTable) {
					await this.loadContextTable({ id })
				}
			} catch (e) {
				if (e.message === 'NOT_FOUND') {
					this.errorMessage = t('tables', 'This table could not be found')
				} else {
					this.errorMessage = t('tables', 'An error occurred while loading the table')
					console.error(e)
				}
			}
		},
	},
}

</script>
<style lang="scss" scoped>
.main-table-view {
	width: max-content;
	min-width: var(--app-content-width, 100%);
}

@page {
	size: auto;
	margin: 5mm;
}

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

@media print {
	html, body {
		background: var(--color-main-background, white) !important;
	}

	html {
		overflow-y: scroll;
	}

	body {
		position: absolute;
	}

	/* hide toast notifications for printing */
	.toastify.dialogs {
		display: none;
	}

	.app-navigation {
		display: none !important;
	}

	#header {
		display: none !important;
	}

	#content-vue {
		display: block !important;
		position: static;
		overflow: auto;
		height: auto;
	}

	#content-vue .row.first-row {
		padding-left: 0px;
	}

	.main-table-view, .main-view-view {
		width: auto;
		min-width: 0;
	}

	.table-dashboard {
		display: none !important;
	}

	.row.space-T {
		display: none !important;
	}

	#app-content-vue .options.row {
		display: none !important;
	}

	#app-content-vue table {
		table-layout: fixed;
	}

	#app-content-vue table td, #app-content-vue table th {
		white-space: normal !important;
		word-break: normal !important;
		word-wrap: break-word !important;
		width: auto !important;
	}

	#app-content-vue table th .clickable {
		overflow-wrap: anywhere;
	}

	#app-content-vue table th .menu {
		display: none !important;
	}

	#app-content-vue table td .tiptap-reader-cell {
		max-height: fit-content;
		max-width: fit-content;
	}

	#app-content-vue table tr > th.sticky:first-child,
	#app-content-vue table tr > td.sticky:first-child,
	#app-content-vue table tr > th.sticky:last-child,
	#app-content-vue table tr > td.sticky:last-child {
		display: none !important;
	}
}
</style>
