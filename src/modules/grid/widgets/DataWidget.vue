<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="data-widget">
		<p v-if="widgetBroken" class="data-widget__empty" data-cy="dataWidgetBroken">
			{{ t('tables', 'This table or view no longer exists') }}
		</p>
		<template v-else-if="!target">
			<p v-if="editable" class="data-widget__empty">
				{{ t('tables', 'Pick a table or view in the widget settings') }}
			</p>
		</template>
		<NcLoadingIcon v-else-if="loading" :size="32" class="data-widget__loading" />
		<p v-else-if="!element" class="data-widget__empty" data-cy="dataWidgetLoadFailed">
			{{ t('tables', 'This table or view could not be loaded') }}
		</p>
		<template v-else>
			<CustomView v-if="isView"
				:view="element"
				:columns="columns"
				:rows="rows"
				:view-setting="viewSetting"
				@create-column="createColumn"
				@import="openImportModal"
				@update:view-setting="setting => viewSetting = setting" />
			<TableWrapper v-else
				:table="element"
				:columns="columns"
				:rows="rows"
				:view-setting="viewSetting"
				@create-column="createColumn"
				@import="openImportModal"
				@update:view-setting="setting => viewSetting = setting" />
		</template>
	</div>
</template>

<script>
import { NcLoadingIcon } from '@nextcloud/vue'
import { mapActions, mapState, storeToRefs } from 'pinia'
import { emit } from '@nextcloud/event-bus'
import TableWrapper from '../../main/sections/TableWrapper.vue'
import CustomView from '../../main/sections/View.vue'
import { useTablesStore } from '../../../store/store.js'
import { useDataStore } from '../../../store/data.js'

/**
 * Shows the rows of one table or view inside a grid view.
 */
export default {
	name: 'DataWidget',
	components: {
		NcLoadingIcon,
		TableWrapper,
		CustomView,
	},
	inject: {
		// Set when the server marked the stored target as gone
		widgetBroken: { default: false },
	},
	props: {
		content: {
			type: Object,
			required: true,
		},
		// The prompt to pick a target only shows while the page is edited
		editable: {
			type: Boolean,
			default: false,
		},
	},
	setup() {
		const store = useDataStore()
		const { getColumns, getRows } = storeToRefs(store)
		return { getColumns, getRows }
	},
	data() {
		return {
			loading: false,
			loadFailed: false,
			viewSetting: {},
		}
	},
	computed: {
		...mapState(useTablesStore, ['tables', 'views']),
		target() {
			return this.content.target ?? null
		},
		isView() {
			return this.target?.type === 'view'
		},
		targetId() {
			return this.target?.id ?? null
		},
		element() {
			if (!this.targetId || this.loadFailed) {
				return null
			}
			return this.isView
				? this.views.find(view => view.id === this.targetId) ?? null
				: this.tables.find(table => table.id === this.targetId) ?? null
		},
		columns() {
			return this.getColumns(this.isView, this.targetId) ?? []
		},
		rows() {
			return this.getRows(this.isView, this.targetId) ?? []
		},
	},
	watch: {
		target: {
			deep: true,
			handler() {
				this.load()
			},
		},
	},
	created() {
		// Counts the loads, so a load that a newer one overtook leaves the state alone
		this.loadRequest = 0
	},
	mounted() {
		this.load()
	},
	methods: {
		...mapActions(useTablesStore, ['loadContextTable', 'loadContextView']),
		...mapActions(useDataStore, ['loadColumnsFromBE', 'loadRowsFromBE', 'loadRelationsFromBE']),
		/**
		 * Loads the table or view with its columns, rows and relations. A target the server
		 * marked as gone is not requested.
		 */
		async load() {
			const request = ++this.loadRequest
			this.loadFailed = false
			if (!this.targetId || this.widgetBroken) {
				this.loading = false
				return
			}
			this.loading = true
			try {
				if (this.isView) {
					await this.loadContextView({ id: this.targetId })
					const view = this.views.find(view => view.id === this.targetId)
					if (view) {
						await this.loadColumnsFromBE({ view })
						await this.loadRowsFromBE({ viewId: view.id, tableId: view.tableId })
						await this.loadRelationsFromBE({ viewId: view.id })
					}
				} else {
					await this.loadContextTable({ id: this.targetId })
					await this.loadColumnsFromBE({ view: null, tableId: this.targetId })
					await this.loadRowsFromBE({ viewId: null, tableId: this.targetId })
					await this.loadRelationsFromBE({ tableId: this.targetId })
				}
			} catch {
				// The store already reported the error
				if (request === this.loadRequest) {
					this.loadFailed = true
				}
			} finally {
				if (request === this.loadRequest) {
					this.loading = false
				}
			}
		},
		/**
		 * Asks the app to open the create column dialog for the shown table or view.
		 */
		createColumn() {
			emit('tables:column:create', { isView: this.isView, element: this.element })
		},
		/**
		 * Asks the app to open the import dialog for the shown table or view.
		 */
		openImportModal() {
			emit('tables:modal:import', { element: this.element, isView: this.isView })
		},
	},
}
</script>

<style lang="scss" scoped>
.data-widget {
	height: 100%;
	overflow: auto;

	&__loading {
		margin: calc(4 * var(--default-grid-baseline)) auto;
	}

	&__empty {
		padding: calc(3 * var(--default-grid-baseline));
		color: var(--color-text-maxcontrast);
	}

	:deep(h1) {
		font-size: unset;
		font-size: revert;
	}
}
</style>
