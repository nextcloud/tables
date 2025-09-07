<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="main-table-view">
		<div v-if="!activeTable && !errorMessage">
			<div class="icon-loading" />
		</div>

		<div v-else-if="activeTable">
			<MainWrapper :element="activeTable" :is-view="false" />
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
		activeTable: {
			handler(newVal, oldVal) {
				if (this.activeTableId && !newVal) {
					if (oldVal) {
						// Table was deleted, redirect to home
						this.$router.push({ path: '/' })
					} else {
						// Table did not exist from the beginning -> ErrorMessage
						this.errorMessage = getNotFoundError('table')
					}
				}
			},
		},
	},

	methods: {
		...mapActions(useTablesStore, ['loadContextTable', 'setActiveTableId']),

		async checkTable() {
			const id = this.activeTableId || this.$route.params.tableId
			if (!id) return

			try {
				await this.loadContextTable({ id })
				this.setActiveTableId(parseInt(id))
			} catch (e) {
				if (e.message === 'NOT_FOUND') {
					this.errorMessage = getNotFoundError('table')
				} else {
					this.errorMessage = getGenericLoadError('table')
					displayError(e, this.errorMessage)
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

:deep(h1) {
	font-size: unset;
	font-size: revert;
}

@page {
	size: auto;
	margin: 5mm;
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
		padding-inline-start: 0px;
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
