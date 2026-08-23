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
				:total-rows="totalRows"
				:view-setting="viewSetting"
				@update:viewSetting="viewSetting = $event"
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
				:total-rows="totalRows"
				:view-setting="viewSetting"
				@update:viewSetting="viewSetting = $event"
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
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import CustomView from './View.vue'
import CustomTable from './Table.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import exportTableMixin from '../../../shared/components/ncTable/mixins/exportTableMixin.js'
import { useTablesStore } from '../../../store/store.js'
import { useDataStore } from '../../../store/data.js'
import { computed } from 'vue'
import { showError } from '@nextcloud/dialogs'
import { buildUrlQuery, parseUrlQuery } from '../../../shared/utils/urlState.js'

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
		const { getColumns, getRows, getTotalRows } = storeToRefs(store)
		// When using storeToRefs, only the top-level state is made reactive.
		// To make nested dynamic keys reactive, you need to use a computed property or watch for changes.
		const rows = computed(() => getRows.value(props.isView, props.element.id))
		const columns = computed(() => getColumns.value(props.isView, props.element.id))
		const totalRows = computed(() => getTotalRows.value(props.isView, props.element.id))
		return { rows, columns, totalRows, dataStore: store }
	},

	data() {
		return {
			localLoading: false,
			reloadInProgress: false,
			viewSettingInProgress: false,
			lastActiveElement: null,
			viewSetting: {},
			lastViewSettingFilter: null,
			lastViewSettingSorting: null,
			lastViewSettingSearchString: null,
			rowsLoading: false,
			rowsPerPage: 100,
			pageNumber: 1,
			paginationOffset: 0,
			applyUrlStateOnReload: false,
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
		viewSetting: {
			handler() {
				const newFilter = this.viewSetting?.filter ? JSON.stringify(this.viewSetting.filter) : null
				const newSorting = this.viewSetting?.sorting ? JSON.stringify(this.viewSetting.sorting) : null
				const newSearchString = this.viewSetting?.searchString || null
				if (newFilter === this.lastViewSettingFilter && newSorting === this.lastViewSettingSorting && newSearchString === this.lastViewSettingSearchString) {
					return
				}
				const oldFilter = this.lastViewSettingFilter
				const oldSorting = this.lastViewSettingSorting
				const oldSearchString = this.lastViewSettingSearchString
				this.lastViewSettingFilter = newFilter
				this.lastViewSettingSorting = newSorting
				this.lastViewSettingSearchString = newSearchString
				this.onViewSettingChanged(oldFilter, oldSorting, oldSearchString)
			},
			deep: true,
		},
		rowsLoading() {
			emit('tables:rows-loading', this.rowsLoading)
		},
	},

	beforeMount() {
		this.applyUrlStateOnReload = true
		this.reload(true)
	},

	mounted() {
		subscribe('tables:pagination-changed', this.onPaginationChanged)
		subscribe('tables:reload', this.onReloadRequested)
	},

	beforeUnmount() {
		unsubscribe('tables:pagination-changed', this.onPaginationChanged)
		unsubscribe('tables:reload', this.onReloadRequested)
	},

	methods: {
		...mapActions(useDataStore, ['removeRows', 'clearState', 'loadColumnsFromBE', 'loadRowsFromBE', 'loadRowsCountFromBE', 'loadRowsForExportFromBE', 'loadRelationsFromBE']),
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

			const viewId = this.isView ? this.element.id : null
			const tableId = !this.isView ? this.element.id : null
			const csv = await this.loadRowsForExportFromBE({
				viewId,
				tableId,
			})
			if (csv) {
				this.downloadFile(csv, this.element.title + '.csv')
			}
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

			if (rows !== this.rows) {
				const viewId = this.isView ? this.element.id : null
				const tableId = !this.isView ? this.element.id : null
				const csv = await this.loadRowsForExportFromBE({
					viewId,
					tableId,
					rowIds: rows.map(row => row.id),
				})
				if (csv) {
					this.downloadFile(csv, this.element.title + '.csv')
				}
				return
			}

			const viewId = this.isView ? this.element.id : null
			const tableId = !this.isView ? this.element.id : null
			const csv = await this.loadRowsForExportFromBE({
				viewId,
				tableId,
				filter: this.viewSetting?.filter,
				sort: this.viewSetting?.sorting,
				search: this.viewSetting?.searchString,
			})
			if (csv) {
				this.downloadFile(csv, this.element.title + '.csv')
			}
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
				this.reloadInProgress = true
				this.localLoading = true

				// Since we show one page at a time, no need keep other tables in the store
				this.clearState()

				if (this.applyUrlStateOnReload) {
					this.applyUrlStateOnReload = false
					this.applyUrlState()
				} else {
					this.viewSetting = {}
					if (this.element?.sort?.length) {
						this.viewSetting.presetSorting = [...this.element.sort]
					}
					this.pageNumber = 1
					this.paginationOffset = 0
				}

				await this.loadColumnsFromBE({
					view: this.isView ? this.element : null,
					tableId: !this.isView ? this.element.id : null,
				})

				await this.loadRelationsFromBE({
					viewId: this.isView ? this.element.id : null,
					tableId: !this.isView ? this.element.id : null,
					force: true,
				})

				if (this.canReadData(this.element)) {
					this.rowsLoading = true
					try {
						await this.loadRowsCountFromBE({
							viewId: this.isView ? this.element.id : null,
							tableId: !this.isView ? this.element.id : null,
							filter: this.viewSetting?.filter,
							sort: this.viewSetting?.sorting,
							search: this.viewSetting?.searchString,
						})
						await this.loadRowsFromBE({
							viewId: this.isView ? this.element.id : null,
							tableId: !this.isView ? this.element.id : null,
							filter: this.viewSetting?.filter,
							sort: this.viewSetting?.sorting,
							search: this.viewSetting?.searchString,
							limit: this.rowsPerPage,
							offset: this.paginationOffset,
						})
					} finally {
						this.rowsLoading = false
					}
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
					const row = this.dataStore.getRows(this.isView, this.element.id).find(r => r.id === this.activeRowId)
					if (row) {
						emit('tables:row:edit', { row, columns: this.dataStore.getColumns(this.isView, this.element.id), isView: this.isView, elementId: this.element.id, element: this.element })
					}
				}
				this.localLoading = false
				this.reloadInProgress = false
				this.$nextTick(() => {
					emit('tables:pagination-changed', { pageNumber: this.pageNumber, rowsPerPage: this.rowsPerPage })
				})
			}
		},
			applyUrlState() {
			const { filter, sorting, searchString, pageNumber, rowsPerPage } = parseUrlQuery(this.$route.query)
			this.pageNumber = pageNumber
			this.rowsPerPage = rowsPerPage
			this.paginationOffset = (this.pageNumber - 1) * this.rowsPerPage
			const viewSetting = {
				filter,
				sorting,
				searchString,
			}
			if (this.element?.sort?.length) {
				viewSetting.presetSorting = [...this.element.sort]
			}
			this.lastViewSettingFilter = viewSetting?.filter ? JSON.stringify(viewSetting.filter) : null
			this.lastViewSettingSorting = viewSetting?.sorting ? JSON.stringify(viewSetting.sorting) : null
			this.lastViewSettingSearchString = viewSetting?.searchString || null
			this.viewSetting = viewSetting
		},

		updateUrlFromState() {
			const query = buildUrlQuery(this.viewSetting, this.pageNumber, this.rowsPerPage)
			this.$router.replace({ query }).catch(() => {})
		},

	async onViewSettingChanged(oldFilter, oldSorting, oldSearchString) {
			if (this.reloadInProgress || this.rowsLoading || !this.element) {
				return
			}
			const filterChanged = this.lastViewSettingFilter !== oldFilter
			const sortingChanged = this.lastViewSettingSorting !== oldSorting
			const searchStringChanged = this.lastViewSettingSearchString !== oldSearchString
			if (!filterChanged && !sortingChanged && !searchStringChanged) {
				return
			}
			this.viewSettingInProgress = filterChanged || searchStringChanged
			this.rowsLoading = true
			try {
				const viewId = this.isView ? this.element.id : null
				const tableId = !this.isView ? this.element.id : null
				if (filterChanged || searchStringChanged) {
					this.pageNumber = 1
					this.paginationOffset = 0
					emit('tables:pagination-changed', { pageNumber: 1, rowsPerPage: this.rowsPerPage })
					await this.loadRowsCountFromBE({
						viewId,
						tableId,
						filter: this.viewSetting?.filter,
						sort: this.viewSetting?.sorting,
						search: this.viewSetting?.searchString,
					})
					await this.loadRowsFromBE({
						viewId,
						tableId,
						filter: this.viewSetting?.filter,
						sort: this.viewSetting?.sorting,
						search: this.viewSetting?.searchString,
						limit: this.rowsPerPage,
						offset: this.paginationOffset,
					})
				} else if (sortingChanged) {
					this.paginationOffset = (this.pageNumber - 1) * this.rowsPerPage
					await this.loadRowsFromBE({
						viewId,
						tableId,
						filter: this.viewSetting?.filter,
						sort: this.viewSetting?.sorting,
						search: this.viewSetting?.searchString,
						limit: this.rowsPerPage,
						offset: this.paginationOffset,
					})
				}
			} finally {
				this.rowsLoading = false
				this.viewSettingInProgress = false
				this.updateUrlFromState()
			}
		},
		async onPaginationChanged({ pageNumber, rowsPerPage }) {
			if (!this.element || this.viewSettingInProgress || this.rowsLoading) {
				return
			}
			if (this.pageNumber === pageNumber && this.rowsPerPage === rowsPerPage) {
				return
			}
			this.pageNumber = pageNumber
			if (rowsPerPage) {
				this.rowsPerPage = rowsPerPage
			}
			this.paginationOffset = (this.pageNumber - 1) * this.rowsPerPage
			this.rowsLoading = true
			const viewId = this.isView ? this.element.id : null
			const tableId = !this.isView ? this.element.id : null
			try {
				await this.loadRowsFromBE({
					viewId,
					tableId,
					filter: this.viewSetting?.filter,
					sort: this.viewSetting?.sorting,
					limit: this.rowsPerPage,
					offset: this.paginationOffset,
				})
			} finally {
				this.rowsLoading = false
				this.updateUrlFromState()
			}
		},
		async onReloadRequested() {
			if (!this.element || this.rowsLoading) {
				return
			}
			this.rowsLoading = true
			const viewId = this.isView ? this.element.id : null
			const tableId = !this.isView ? this.element.id : null
			try {
				await this.loadRowsCountFromBE({
					viewId,
					tableId,
					filter: this.viewSetting?.filter,
					sort: this.viewSetting?.sorting,
				})
				await this.loadRowsFromBE({
					viewId,
					tableId,
					filter: this.viewSetting?.filter,
					sort: this.viewSetting?.sorting,
					limit: this.rowsPerPage,
					offset: this.paginationOffset,
				})
			} finally {
				this.rowsLoading = false
			}
		},
	},
}
</script>
