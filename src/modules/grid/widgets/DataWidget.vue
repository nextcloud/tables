<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="data-widget">
		<p v-if="!target && editable" class="data-widget__empty" data-cy="dataWidgetPickSource">
			{{ t('tables', 'Pick a table or view in the widget settings') }}
		</p>
		<NcEmptyContent v-else-if="!target"
			class="data-widget__no-source"
			:name="t('tables', 'No table or view to show')"
			data-cy="dataWidgetNoSource">
			<template #icon>
				<TableOff :size="20" />
			</template>
		</NcEmptyContent>
		<NcLoadingIcon v-else-if="loading" :size="32" class="data-widget__loading" />
		<p v-else-if="!element" class="data-widget__empty" data-cy="dataWidgetLoadFailed">
			{{ t('tables', 'This table or view could not be loaded') }}
		</p>
		<template v-else>
			<CustomView v-if="isView"
				:show-title="showTitle"
				:view="element"
				:columns="columns"
				:rows="rows"
				:view-setting="viewSetting"
				@create-column="createColumn"
				@import="openImportModal"
				@download-csv="downloadCSV(rows)"
				@download-filtered-csv="downloadCSV"
				@toggle-share="openSidebar('tables:sidebar:sharing', 'sharing')"
				@show-integration="openSidebar('tables:sidebar:integration', 'integration')"
				@update:view-setting="setting => viewSetting = setting" />
			<TableWrapper v-else
				:show-title="showTitle"
				:table="element"
				:columns="columns"
				:rows="rows"
				:view-setting="viewSetting"
				@create-column="createColumn"
				@import="openImportModal"
				@import-scheme="openImportSchemeModal"
				@download-csv="downloadCSV(rows)"
				@download-filtered-csv="downloadCSV"
				@toggle-share="openSidebar('tables:sidebar:sharing', 'sharing')"
				@show-integration="openSidebar('tables:sidebar:integration', 'integration')"
				@update:view-setting="setting => viewSetting = setting" />
		</template>
	</div>
</template>

<script>
import { NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import TableOff from 'vue-material-design-icons/TableOff.vue'
import { mapActions, mapState, storeToRefs } from 'pinia'
import { emit } from '@nextcloud/event-bus'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import TableWrapper from '../../main/sections/TableWrapper.vue'
import CustomView from '../../main/sections/View.vue'
import { useTablesStore } from '../../../store/store.js'
import { useDataStore } from '../../../store/data.js'
import exportTableMixin from '../../../shared/components/ncTable/mixins/exportTableMixin.js'
import { presetViewSetting } from '../../../shared/utils/viewSetting.js'

/**
 * Shows the rows of one table or view of the application on a grid page, with the
 * components the start page uses.
 */
export default {
	name: 'DataWidget',
	components: {
		NcEmptyContent,
		NcLoadingIcon,
		TableOff,
		TableWrapper,
		CustomView,
	},
	mixins: [exportTableMixin],
	props: {
		// The table or view to show, as {type: 'table'|'view', id}; null when the widget has none,
		// also when its table or view left the application
		target: {
			type: Object,
			default: null,
		},
		// The prompt to pick a target only shows while the page is edited
		editable: {
			type: Boolean,
			default: false,
		},
		// False inside a widget card, which shows the title of the table or view itself
		showTitle: {
			type: Boolean,
			default: true,
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
			// The table or view the view setting was started for
			seededFor: null,
		}
	},
	computed: {
		...mapState(useTablesStore, ['tables', 'views']),
		isView() {
			return this.target?.type === 'view'
		},
		targetId() {
			return this.target?.id ?? null
		},
		// The parent builds a new target object on every render, so changes are told by this key
		targetKey() {
			return this.target ? this.target.type + ':' + this.target.id : null
		},
		// What decides the rows and columns of a shown view; null for a table
		viewQueryKey() {
			if (!this.isView || !this.element) {
				return null
			}
			const { filter, sort, columns, columnSettings } = this.element
			return JSON.stringify({ filter, sort, columns, columnSettings })
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
		targetKey() {
			this.load()
		},
		// A view saved from inside the widget, for example with a new filter, shows its new rows
		viewQueryKey(key) {
			if (key !== null && this.loadedViewQueryKey !== null && key !== this.loadedViewQueryKey) {
				this.load(true)
			}
		},
		// A loaded table or view starts in the sorting it is saved with. Loading it again keeps
		// what the viewer searched, filtered and sorted; only a change of its saved sorting
		// is taken over.
		element() {
			this.seedViewSetting()
		},
		'element.sort'(sort, previous) {
			if (this.seededFor !== this.targetKey || JSON.stringify(sort ?? []) === JSON.stringify(previous ?? [])) {
				return
			}
			const { presetSorting, ...viewSetting } = this.viewSetting
			this.viewSetting = { ...viewSetting, ...presetViewSetting(this.element) }
		},
	},
	created() {
		// Counts the loads, so a load that a newer one overtook leaves the state alone
		this.loadRequest = 0
		// The view query of the last finished load, null while loading
		this.loadedViewQueryKey = null
		this.resizeObserver = null
	},
	mounted() {
		this.seedViewSetting()
		this.load()
		// The table sizes itself from --app-content-width, which is the width of the widget here
		this.resizeObserver = new ResizeObserver(entries => {
			const width = entries[0]?.contentRect.width ?? 0
			this.$el.style.setProperty('--app-content-width', `${width}px`)
		})
		this.resizeObserver.observe(this.$el)
	},
	beforeUnmount() {
		this.resizeObserver?.disconnect()
		this.resizeObserver = null
	},
	methods: {
		...mapActions(useTablesStore, ['loadContextTable', 'loadContextView', 'setActiveTableId', 'setActiveViewId', 'validateExportAccess']),
		...mapActions(useDataStore, ['loadColumnsFromBE', 'loadRowsFromBE', 'loadRelationsFromBE']),
		/**
		 * Loads the table or view with its columns, rows and relations.
		 *
		 * @param {boolean} force also load the relations when they were loaded before
		 */
		async load(force = false) {
			const request = ++this.loadRequest
			this.loadFailed = false
			this.loadedViewQueryKey = null
			if (!this.targetId) {
				this.loading = false
				return
			}
			this.loading = true
			try {
				if (this.isView) {
					await this.loadContextView({ id: this.targetId })
					const view = this.views.find(view => view.id === this.targetId)
					if (view) {
						await Promise.all([
							this.loadColumnsFromBE({ view }),
							this.loadRowsFromBE({ viewId: view.id, tableId: view.tableId }),
							this.loadRelationsFromBE({ viewId: view.id, force }),
						])
					}
				} else {
					await this.loadContextTable({ id: this.targetId })
					await Promise.all([
						this.loadColumnsFromBE({ view: null, tableId: this.targetId }),
						this.loadRowsFromBE({ viewId: null, tableId: this.targetId }),
						this.loadRelationsFromBE({ tableId: this.targetId, force }),
					])
				}
				if (request === this.loadRequest) {
					this.loadedViewQueryKey = this.viewQueryKey
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
		 * Starts the view setting of the shown table or view, once for each.
		 */
		seedViewSetting() {
			if (!this.element || this.seededFor === this.targetKey) {
				return
			}
			this.seededFor = this.targetKey
			this.viewSetting = presetViewSetting(this.element)
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
		/**
		 * Asks the app to open the scheme import dialog for the shown table.
		 */
		openImportSchemeModal() {
			emit('tables:modal:table-scheme-import', { table: this.element })
		},
		/**
		 * Downloads rows of the shown table or view as CSV, after checking the user may still read it.
		 *
		 * @param {Array} rows the rows to download
		 */
		async downloadCSV(rows) {
			const access = await this.validateExportAccess({ id: this.element.id, isView: this.isView })
			if (!access?.ok) {
				if (access?.reason === 'NO_ACCESS') {
					showError(t('tables', 'Your access was revoked. Reload the page to update your permissions.'))
				}
				return
			}
			this.downloadCsv(rows, this.columns, this.element.title)
		},
		/**
		 * Opens a tab of the sidebar for the shown table or view, which the sidebar shows as the
		 * active element.
		 *
		 * @param {string} event the event bus event that opens the tab
		 * @param {string} tab the tab to open
		 */
		openSidebar(event, tab) {
			if (this.isView) {
				this.setActiveViewId(this.targetId)
			} else {
				this.setActiveTableId(this.targetId)
			}
			emit(event, { open: true, tab })
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

	&__no-source {
		margin-top: calc(4 * var(--default-grid-baseline));
	}

	:deep(h1) {
		font-size: unset;
		font-size: revert;
	}

	// Without the title row of the table or view, its sticky options and head move up by its height
	&:not(:has(.first-row)) {
		:deep(.options.row) {
			top: 0;
		}

		:deep(thead) {
			top: 48px;
		}
	}
}
</style>
