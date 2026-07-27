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
				:view-setting.sync="viewSetting"
				@create-column="createColumn"
				@import="openImportModal"
				@download-csv="downloadCSV"
				@download-filtered-csv="downloadFilteredCSV"
				@toggle-share="toggleShare"
				@show-integration="showIntegration" />
			<CustomTable v-else
				:table="element"
				:columns="columns"
				:rows="rows"
				:view-setting.sync="viewSetting"
				@create-column="createColumn"
				@import="openImportModal"
				@download-csv="downloadCSV"
				@download-filtered-csv="downloadFilteredCSV"
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
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { buildBackendFilterQuery } from '../../../shared/components/ncTable/mixins/filterSupport.js'
import { getExplicitSortRules, normalizeBackendSortRulesForColumns } from '../../../shared/components/ncTable/mixins/sortSupport.js'

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
			isRefreshing: false,
		}
	},

	computed: {
		...mapState(useTablesStore, ['activeRowId']),
		backendQueryKey() {
			return JSON.stringify(this.buildRefreshQuery())
		},
	},

	watch: {
		element() {
			this.reload()
		},
		activeRowId() {
			this.reload()
		},
		backendQueryKey() {
			if (!this.element || this.localLoading || this.isRefreshing || !this.lastActiveElement) {
				return
			}

			this.refreshRows()
		},
	},

	beforeMount() {
		this.reload(true)
	},

	methods: {
		...mapActions(useDataStore, ['removeRows', 'clearState', 'loadColumnsFromBE', 'loadRowsFromBE', 'loadRelationsFromBE']),
		...mapActions(useTablesStore, ['validateExportAccess']),
		createColumn() {
			emit('tables:column:create', { isView: this.isView, element: this.element })
		},
		async downloadCSV() {
			const access = await this.validateExportAccess({
				id: this.element.id,
				isView: this.isView,
			})

			if (!access?.ok) {
				if (access?.reason === 'NO_ACCESS') {
					showError(t('tables', 'Your access was revoked. Reload the page to update your permissions.'))
				}
				return
			}

			this.downloadCsv(this.rows, this.columns, this.element.title)
		},
		async downloadFilteredCSV(rows) {
			const access = await this.validateExportAccess({
				id: this.element.id,
				isView: this.isView,
			})

			if (!access?.ok) {
				if (access?.reason === 'NO_ACCESS') {
					showError(t('tables', 'Your access was revoked. Reload the page to update your permissions.'))
				}
				return
			}

			this.downloadCsv(rows, this.columns, this.element.title)
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
		buildRefreshQuery() {
			const columns = this.columns || []
			const effectiveSort = normalizeBackendSortRulesForColumns(getExplicitSortRules(this.viewSetting), columns)
			const customFilters = buildBackendFilterQuery(this.$route.query.customFilters, this.viewSetting, columns)

			return {
				customFilters,
				sort: effectiveSort.length > 0 ? JSON.stringify(effectiveSort) : null,
			}
		},
		async refreshRows() {
			this.isRefreshing = true
			try {
				await this.syncRows({ showRefreshError: true })
			} finally {
				this.isRefreshing = false
			}
		},
		async syncRows({ showRefreshError = false } = {}) {
			if (!this.canReadData(this.element)) {
				await this.removeRows({
					isView: this.isView,
					elementId: this.element.id,
				})
				return
			}

			const refreshQuery = this.buildRefreshQuery()
			const rowsLoaded = await this.loadRowsFromBE({
				viewId: this.isView ? this.element.id : null,
				tableId: this.isView ? null : this.element.id,
				customFilters: refreshQuery.customFilters,
				sort: refreshQuery.sort,
			})

			if (showRefreshError && rowsLoaded === false) {
				showError(t('tables', 'Error refreshing, please try to reload the whole page'))
			}
		},
		async reload(force = false, manualRefresh = false) {
			if (!this.element) {
				return
			}

			const sameElement = this.lastActiveElement
				&& this.element.id === this.lastActiveElement.id
				&& this.isView === this.lastActiveElement.isView

			if (sameElement && !force) {
				return
			}

			if (manualRefresh) {
				this.isRefreshing = true
			} else {
				this.localLoading = true
				this.clearState()
				this.viewSetting = {}
				if (this.element?.sort?.length) {
					this.viewSetting.presetSorting = [...this.element.sort]
				}
			}

			try {
				await this.loadColumnsFromBE({
					view: this.isView ? this.element : null,
					tableId: !this.isView ? this.element.id : null,
				})

				await this.loadRelationsFromBE({
					viewId: this.isView ? this.element.id : null,
					tableId: !this.isView ? this.element.id : null,
					force: true,
				})

				await this.syncRows({ showRefreshError: manualRefresh })

				this.lastActiveElement = {
					id: this.element.id,
					isView: this.isView,
				}
				if (this.activeRowId) {
					emit('tables:row:edit', { row: this.rows.find(r => r.id === this.activeRowId), columns: this.columns, isView: this.isView, elementId: this.element.id, element: this.element })
				}
			} finally {
				this.localLoading = false
				this.isRefreshing = false
			}
		},
	},
}
</script>
