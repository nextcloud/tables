<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<div v-if="localLoading || !element" class="icon-loading" />

		<div v-else>
			<CustomView v-if="isView"
				:view="element"
				:columns="columns"
				:rows="rows"
				:view-setting="viewSetting"
				@create-column="createColumn"
				@import="openImportModal"
				@download-csv="downloadCSV"
				@toggle-share="toggleShare"
				@show-integration="showIntegration" />
			<CustomTable v-else
				:table="element"
				:columns="columns"
				:rows="rows"
				:view-setting="viewSetting"
				@create-column="createColumn"
				@import="openImportModal"
				@download-csv="downloadCSV"
				@toggle-share="toggleShare"
				@show-integration="showIntegration" />
		</div>
	</div>
</template>

<script>

import { mapState, mapActions, storeToRefs } from 'pinia'
import { emit } from '@nextcloud/event-bus'
import CustomView from './View.vue'
import CustomTable from './Table.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import exportTableMixin from '../../../shared/components/ncTable/mixins/exportTableMixin.js'
import { useTablesStore } from '../../../store/store.js'
import { useDataStore } from '../../../store/data.js'
import { computed } from 'vue'

export default {
	name: 'MainWrapper',

	components: {
		CustomView,
		CustomTable,
	},

	mixins: [permissionsMixin, exportTableMixin],

	props: {
		element: {
			type: Object,
			default: null,
		},
		isView: {
			type: Boolean,
			default: false,
		},
	},
	setup(props) {
		const store = useDataStore()
		const { getColumns, getRows } = storeToRefs(store)
		// When using storeToRefs, only the top-level state is made reactive.
		// To make nested dynamic keys reactive, you need to use a computed property or watch for changes.
		const rows = computed(() => getRows.value(props.isView, props.element.id))
		const columns = computed(() => getColumns.value(props.isView, props.element.id))
		return { rows, columns }
	},

	data() {
		return {
			localLoading: false,
			lastActiveElement: null,
			viewSetting: {},
		}
	},

	computed: {
		...mapState(useTablesStore, ['activeRowId']),
	},

	watch: {
		element() {
			this.reload()
		},
		activeRowId() {
			this.reload()
		},
	},

	beforeMount() {
		this.reload(true)
	},

	methods: {
		...mapActions(useDataStore, ['removeRows', 'clearState', 'loadColumnsFromBE', 'loadRowsFromBE']),
		createColumn() {
			emit('tables:column:create', { isView: this.isView, element: this.element })
		},
		downloadCSV() {
			this.downloadCsv(this.rows, this.columns, this.element.title)
		},
		toggleShare() {
			emit('tables:sidebar:sharing', { open: true, tab: 'sharing' })
		},
		showIntegration() {
			emit('tables:sidebar:integration', { open: true, tab: 'integration' })
		},
		openImportModal() {
			emit('tables:modal:import', { element: this.element, isView: this.isView })
		},
		deleteRows(rowIds) {
			this.rowsToDelete = rowIds
		},
		async reload(force = false) {
			if (!this.element) {
				return
			}

			// Used to reload View from backend, in case there are Filter updates
			const isLastElementSameAndView = this.element.id === this.lastActiveElement?.id && this.isView === this.lastActiveElement?.isView

			if (!this.lastActiveElement || this.element.id !== this.lastActiveElement.id || isLastElementSameAndView || this.isView !== this.lastActiveElement.isView || force) {
				this.localLoading = true

				// Since we show one page at a time, no need keep other tables in the store
				this.clearState()

				this.viewSetting = {}
				if (this.isView && this.element?.sort?.length) {
					this.viewSetting.presetSorting = [...this.element.sort]
				}

				await this.loadColumnsFromBE({
					view: this.isView ? this.element : null,
					tableId: !this.isView ? this.element.id : null,
				})
				if (this.canReadData(this.element)) {
					await this.loadRowsFromBE({
						viewId: this.isView ? this.element.id : null,
						tableId: !this.isView ? this.element.id : null,
					})
				} else {
					await this.removeRows({
						isView: this.isView,
						elementId: this.element.id,
					})
				}
				this.lastActiveElement = {
					id: this.element.id,
					isView: this.isView,
				}
				if (this.activeRowId) {
					emit('tables:row:edit', { row: this.rows.find(r => r.id === this.activeRowId), columns: this.columns, isView: this.isView, elementId: this.element.id, element: this.element })
				}
				this.localLoading = false
			}
		},
	},
}
</script>
