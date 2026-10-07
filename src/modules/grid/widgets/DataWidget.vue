<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="data-widget">
		<div v-if="loading" class="icon-loading" />
		<p v-else-if="!element" class="data-widget__empty">
			{{ t('tables', 'Pick a table or view in the widget settings') }}
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
		TableWrapper,
		CustomView,
	},
	props: {
		content: {
			type: Object,
			required: true,
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
			if (this.content.target) {
				return this.content.target
			}
			// grids saved before the target became one property
			return this.content.targetType ? { type: this.content.targetType, id: this.content.targetId } : null
		},
		isView() {
			return this.target?.type === 'view'
		},
		targetId() {
			return parseInt(this.target?.id)
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
	mounted() {
		this.load()
	},
	methods: {
		...mapActions(useTablesStore, ['loadContextTable', 'loadContextView']),
		...mapActions(useDataStore, ['loadColumnsFromBE', 'loadRowsFromBE', 'loadRelationsFromBE']),
		async load() {
			this.loadFailed = false
			if (!this.targetId) {
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
			} catch (error) {
				console.error('The widget could not load its data', error)
				this.loadFailed = true
			} finally {
				this.loading = false
			}
		},
		createColumn() {
			emit('tables:column:create', { isView: this.isView, element: this.element })
		},
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

	&__empty {
		padding: calc(3 * var(--default-grid-baseline, 4px));
		color: var(--color-text-maxcontrast);
	}

	:deep(h1) {
		font-size: unset;
		font-size: revert;
	}
}
</style>
