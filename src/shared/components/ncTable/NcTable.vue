<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<!--

This is a reusable component. There are no outside dependency's.

Emitted events
==============
edit-row                -> click on edit button in a row
update-selected-rows    -> send an array with selected row IDs
create-row              -> click on create (plus) button
create-column
edit-columns
delete-selected-rows
set-search-string       -> @param string
import                  -> click on import button on tables menu

Props
=====
rows <array>            -> Array with row-objects { "columnId": 1, "value": "some" }
columns <array>         -> Array with column-objects { "id":2, "tableId":1, "title":"Description", ... }
config                  -> config object for the table
  options
    show-create-row [true]
    show-delete-rows [true]
  table
    rows
      show-delete-button [false]
      show-edit-button [true]
      action-buttons-position [right]
      action-button-sticky [true]
    columns
      show-inline-edit-button [true]

Bus events
==========
deselect-all-rows        -> unselect all rows, e.g. after deleting selected rows

-->

<template>
	<div ref="table" class="NcTable" data-cy="ncTable">
		<div class="options row" style="padding-right: calc(var(--default-grid-baseline) * 2);">
			<Options :rows="getSearchedAndFilteredAndSortedRows" :all-rows="rows" :columns="parsedColumns" :element-id="elementId" :is-view="isView"
				:selected-rows="localSelectedRows" :show-options="parsedColumns.length !== 0"
				:view-setting.sync="localViewSetting" :config="config" @create-row="$emit('create-row')"
				@download-csv="data => downloadCsv(data, parsedColumns, downloadTitle)"
				@set-search-string="str => setSearchString(str)"
				@delete-selected-rows="rowIds => $emit('delete-selected-rows', rowIds)" />
		</div>
		<div class="custom-table row">
			<CustomTable v-if="config.canReadRows || (config.canCreateRows && rows.length > 0)" :columns="parsedColumns"
				:rows="getSearchedAndFilteredAndSortedRows" :is-view="isView" :element-id="elementId" :view-setting.sync="localViewSetting"
				:config="config" @create-row="$emit('create-row')"
				@edit-row="rowId => $emit('edit-row', rowId)"
				@create-column="$emit('create-column')"
				@edit-column="col => $emit('edit-column', col)"
				@delete-column="col => $emit('delete-column', col)"
				@update-selected-rows="rowIds => localSelectedRows = rowIds"
				@download-csv="data => downloadCsv(data, parsedColumns, table)">
				<template #actions>
					<slot name="actions" />
				</template>
			</CustomTable>
			<NcEmptyContent v-else-if="config.canCreateRows && rows.length === 0"
				:name="t('tables', 'Create rows')"
				:description="t('tables', 'You are not allowed to read this table, but you can still create rows.')">
				<template #icon>
					<Plus :size="25" />
				</template>
				<template #action>
					<NcButton :aria-label="t('tables', 'Create row')" type="primary"
						@click="$emit('create-row')">
						<template #icon>
							<Plus :size="25" />
						</template>
						{{ t('tables', 'Create row') }}
					</NcButton>
				</template>
			</NcEmptyContent>
			<NcEmptyContent v-else
				:name="t('tables', 'No permissions')"
				:description="t('tables', 'You have no permissions for this table.')">
				<template #icon>
					<Cancel :size="25" />
				</template>
			</NcEmptyContent>
		</div>
	</div>
</template>

<script>

import Options from './sections/Options.vue'
import CustomTable from './sections/CustomTable.vue'
import exportTableMixin from './mixins/exportTableMixin.js'
import { NcEmptyContent, NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { parseCol } from './mixins/columnParser.js'
import { AbstractColumn } from './mixins/columnClass.js'
import { translate as t } from '@nextcloud/l10n'
import {
	TYPE_META_CREATED_AT,
	TYPE_META_CREATED_BY,
	TYPE_META_ID,
	TYPE_META_UPDATED_AT,
	TYPE_META_UPDATED_BY,
} from '../../constants.ts'
import { MetaColumns } from './mixins/metaColumns.js'
import { MagicFields } from './mixins/magicFields.js'
import { getFiltersForColumn } from './mixins/filter.js'

export default {
	name: 'NcTable',

	components: { CustomTable, Options, NcButton, NcEmptyContent, Plus, Cancel },

	mixins: [exportTableMixin],

	props: {
		rows: {
			type: Array,
			default: () => [],
		},
		columns: {
			type: Array,
			default: () => [],
		},
		elementId: {
			type: Number,
			default: null,
		},
		isView: {
			type: Boolean,
			default: true,
		},
		downloadTitle: {
			type: String,
			default: t('tables', 'Download'),
		},
		viewSetting: {
			type: Object,
			default: null,
		},
		selectedRows: {
			type: Array,
			default: null,
		},
		canReadRows: {
			type: Boolean,
			default: true,
		},
		canCreateRows: {
			type: Boolean,
			default: true,
		},
		canEditRows: {
			type: Boolean,
			default: true,
		},
		canDeleteRows: {
			type: Boolean,
			default: true,
		},
		canCreateColumns: {
			type: Boolean,
			default: true,
		},
		canEditColumns: {
			type: Boolean,
			default: true,
		},
		canDeleteColumns: {
			type: Boolean,
			default: true,
		},
		canDeleteTable: {
			type: Boolean,
			default: true,
		},
		canSelectRows: {
			type: Boolean,
			default: true,
		},
		canHideColumns: {
			type: Boolean,
			default: true,
		},
		canFilter: {
			type: Boolean,
			default: true,
		},
		showActions: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			localSelectedRows: [],
			localViewSetting: this.viewSetting ?? {},
			viewerAppAvailable: !!window.OCA?.Viewer?.open,
			table: null,
		}
	},
	computed: {
		config() {
			return {
				canReadRows: this.canReadRows,
				canCreateRows: this.canCreateRows,
				canEditRows: this.canEditRows,
				canDeleteRows: this.canDeleteRows,
				canCreateColumns: this.canCreateColumns,
				canEditColumns: this.canEditColumns,
				canDeleteColumns: this.canDeleteColumns,
				canDeleteTable: this.canDeleteTable,
				canSelectRows: this.canSelectRows,
				canHideColumns: this.canHideColumns,
				canFilter: this.canFilter,
				showActions: this.showActions,
			}
		},
		parsedColumns() {
			if (this.columns.length && !(this.columns[0] instanceof AbstractColumn)) {
				return this.columns.map(col => parseCol(col))
			}
			return this.columns
		},

		sorting() {
			return this.viewSetting?.sorting
		},
		getSearchedAndFilteredRows() {
			const debug = false
			// if we don't have to search and/or filter
			if (!this.viewSetting?.filter?.length > 0 && !this.viewSetting?.searchString) {
				// cleanup markers
				if (this.rows && this.columns) {
					this.rows.forEach(row => {
						if (row && row.data) {
							this.columns.forEach(column => {
								const cell = row.data.find(item => item && item.columnId === column.id)
								if (cell === undefined) {
									return
								}
								delete cell.searchStringFound
								delete cell.filterFound
							})
						}
					})
				}
				return this.rows || []
			}

			const data = [] // array of rows
			const searchString = this.viewSetting?.searchString
			// each row
			if (!this.rows || !this.columns) {
				return []
			}

			for (const row of this.rows) {
				if (!row || !row.data) {
					continue
				}

				if (debug) {
					console.debug('new row ===============================================', row)
				}
				let filterStatusRow = null
				let searchStatusRow = false

				// each column in a row => cell
				this.columns.forEach(column => {
					if (debug) {
						console.debug('new column -------------------', column)
					}
					let filterStatus = null
					let searchStatus = true
					const filters = getFiltersForColumn(column, this.viewSetting)
					let cell
					if (column.id < 0) {
						cell = { columnId: column.id }
						switch (column.id) {
						case TYPE_META_ID:
							cell.value = row.id
							break
						case TYPE_META_CREATED_BY:
							cell.value = row.createdBy
							break
						case TYPE_META_UPDATED_BY:
							cell.value = row.editedBy
							break
						case TYPE_META_CREATED_AT:
							cell.value = row.createdAt
							break
						case TYPE_META_UPDATED_AT:
							cell.value = row.editedAt
							break
						}
					} else {
						cell = row.data.find(item => item && item.columnId === column.id)
					}

					// if we don't have a value for this cell
					if (cell === undefined) {
						if (searchString) {
							searchStatus = false
						}
						cell = { columnId: column.id, value: null }
					}
					// cleanup possible old markers
					delete cell.searchStringFound
					delete cell.filterFound

					// apply filters (if any)
					filters.forEach(fil => {
						this.addMagicFieldsValues(fil)
						if (filterStatus === null || filterStatus === true) {
							filterStatus = column.isFilterFound(cell, fil)
						}
					})
					// if we should search
					if (searchString) {
						console.debug('look for searchString', searchString)
						searchStatus = column.isSearchStringFound(cell, searchString.toLowerCase())
					}

					if (debug) {
						console.debug('filterStatus for cell', { cell: cell?.value, filterStatusCell: filterStatus, filterStatusRowBefore: filterStatusRow })
					}

					// if filterStatus is null, this result should be ignored
					if (filterStatus !== null && (filterStatusRow || filterStatusRow === null)) {
						filterStatusRow = filterStatus
					}

					if (debug) {
						console.debug('new filterStatusRow', filterStatusRow)
					}

					// filterStatusRow = filterStatus
					searchStatusRow = searchStatusRow || searchStatus
				})

				if (debug) {
					console.debug('if push row', { filterStatusRow, searchStatusRow, result: (filterStatusRow || filterStatusRow === null) && searchStatusRow })
				}
				if ((filterStatusRow || filterStatusRow === null) && searchStatusRow) {
					data.push({ ...row })
				}
			}
			return data
		},
		getSearchedAndFilteredAndSortedRows() {
			const allColumns = this.columns.concat(MetaColumns)
			const sort = (sortCols) => {
				const sortColumn = allColumns.find(item => item.id === sortCols?.[0].columnId)
				const nextSorts = []
				for (let i = 1; i < sortCols.length; i++) {
					const sortColumn = allColumns.find(item => item.id === sortCols[i].columnId)
					nextSorts.push(sortColumn?.sort?.(sortCols[i].mode))
				}
				return [...this.getSearchedAndFilteredRows].sort(sortColumn?.sort?.(sortCols[0].mode, nextSorts))
			}

			// if we have to sort
			if (this.viewSetting?.presetSorting) {
				return sort(this.viewSetting.presetSorting)
			}
			if (this.viewSetting?.sorting) {
				return sort(this.viewSetting.sorting)
			}
			return this.getSearchedAndFilteredRows
		},
	},
	watch: {
		localSelectedRows() {
			this.$emit('update:selectedRows', this.localSelectedRows)
		},
		localViewSetting() {
			this.$emit('update:viewSetting', this.localViewSetting)
		},
		viewSetting() {
			this.localViewSetting = this.viewSetting
		},
	},
	mounted() {
		subscribe('tables:selected-rows:deselect', ({ elementId, isView }) => this.deselectRows(elementId, isView))

		if (this.viewerAppAvailable) {
			this.$refs.table.addEventListener('click', function(event) {
				const $img = event.target.closest('.image_container.widget-file img')
				if (!$img) {
					return
				}

				const filePath = decodeURIComponent($img.src.match(/\/dav\/files\/(.+?)\/(.+)/)[2])
				OCA.Viewer.open({ path: filePath, list: [{ filename: filePath }] })
			})
		}
	},
	beforeDestroy() {
		unsubscribe('tables:selected-rows:deselect', ({ elementId, isView }) => this.deselectRows(elementId, isView))
	},
	methods: {
		t,
		deselectRows(elementId, isView) {
			if (parseInt(elementId) === parseInt(this.elementId) && isView === this.isView) {
				this.localSelectedRows = []
			}
		},
		setSearchString(str) {
			this.localViewSetting.searchString = str !== '' ? str : null
			this.localViewSetting = JSON.parse(JSON.stringify(this.localViewSetting))
		},
		addMagicFieldsValues(filter) {
			Object.values(MagicFields).forEach(field => {
				const newFilterValue = filter.value.replace('@' + field.id, field.replace)
				if (filter.value !== newFilterValue) {
					filter.magicValuesEnriched = newFilterValue
				}
			})
		},
	},
}
</script>

<style scoped lang="scss">
.options.row {
	width: var(--app-content-width, auto);
	position: sticky;
	top: 60px;
	inset-inline-start: 0;
	z-index: 15;
	background-color: var(--color-main-background);
	padding-top: 4px; // fix to show buttons completely
	padding-bottom: 4px; // to make it nice with the padding-top
}
</style>

<style lang="scss" scoped>
.image_container.widget-file {
	height: auto !important;
}

.image_container.widget-file img {
	cursor: pointer !important;
}
</style>
