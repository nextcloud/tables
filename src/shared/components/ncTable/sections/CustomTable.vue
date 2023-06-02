<template>
	<div class="container">
		<table>
			<thead>
				<TableHeader :columns="columns"
					:selected-rows="selectedRows"
					:rows="getSearchedAndFilteredAndSortedRows"
					:table="table"
					:view="view"
					@create-row="$emit('create-row')"
					@import="table => $emit('import', table)"
					@create-column="$emit('create-column')"
					@edit-columns="$emit('edit-columns')"
					@add-filter="filter => $emit('add-filter', filter)"
					@download-csv="data => $emit('download-csv', data)"
					@select-all-rows="selectAllRows"
					@delete-filter="id => $emit('delete-filter', id)" />
			</thead>
			<tbody>
				<TableRow v-for="(row, index) in getSearchedAndFilteredAndSortedRows"
					:key="index"
					:row="row"
					:columns="columns"
					:selected="isRowSelected(row.id)"
					@update-row-selection="updateRowSelection"
					@edit-row="rowId => $emit('edit-row', rowId)" />
			</tbody>
		</table>
	</div>
</template>

<script>
import TableHeader from '../partials/TableHeader.vue'
import TableRow from '../partials/TableRow.vue'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { mapGetters } from 'vuex'
import textLineMixin from '../mixins/columnsTypes/textLineMixin.js'
import textLinkMixin from '../mixins/columnsTypes/textLinkMixin.js'
import selectionMixin from '../mixins/columnsTypes/selectionMixin.js'
import selectionMultiMixin from '../mixins/columnsTypes/selectionMultiMixin.js'
import numberMixin from '../mixins/columnsTypes/numberMixin.js'
import numberStarsMixin from '../mixins/columnsTypes/numberStarsMixin.js'
import numberProgressMixin from '../mixins/columnsTypes/numberProgressMixin.js'
import textLongMixin from '../mixins/columnsTypes/textLongMixin.js'
import selectionCheckMixin from '../mixins/columnsTypes/selectionCheckMixin.js'
import datetimeDateMixin from '../mixins/columnsTypes/datetimeDateMixin.js'
import datetimeTimeMixin from '../mixins/columnsTypes/datetimeTimeMixin.js'
import datetimeMixin from '../mixins/columnsTypes/datetimeMixin.js'
import generalHelper from '../../../mixins/generalHelper.js'
import searchAndFilterMixin from '../mixins/searchAndFilterMixin.js'
import textRichMixin from '../mixins/columnsTypes/textRichMixin.js'

export default {
	name: 'CustomTable',

	components: {
		TableRow,
		TableHeader,
	},

	mixins: [
		textLineMixin,
		textLongMixin,
		textRichMixin,
		selectionMixin,
		selectionMultiMixin,
		numberMixin,
		generalHelper,
		searchAndFilterMixin,
		selectionCheckMixin,
		textLinkMixin,
		numberStarsMixin,
		numberProgressMixin,
		datetimeDateMixin,
		datetimeTimeMixin,
		datetimeMixin,
	],

	props: {
		rows: {
			type: Array,
			default: () => [],
		},
		columns: {
			type: Array,
			default: () => [],
		},
		table: {
			type: Object,
			default: () => {},
		},
		view: {
		      type: Object,
		      default: null,
		    },
	},

	data() {
		return {
			selectedRows: [],
			searchTerm: null,
		}
	},

	computed: {

		...mapGetters(['getColumnById']),

		getSearchedAndFilteredAndSortedRows() {
			// if we have to sort
			if (this.view.sorting) {
				const sortColumn = this.columns.find(item => item.id === this.view.sorting[0].columnId)
				const sortMethodName = 'sorting' + this.ucfirst(sortColumn.type) + this.ucfirst(sortColumn.subtype)
				if (!(this[sortMethodName] instanceof Function)) {
					console.error('the needed method is missing', { sortColumn, sortMethodName, method: this[sortMethodName], this: this })
				}
				return [...this.getSearchedAndFilteredRows].sort(this[sortMethodName](sortColumn, this.view.sorting[0].mode))
			}
			return this.getSearchedAndFilteredRows
		},
		getSearchedAndFilteredRows() {
			const debug = false
			// if we don't have to search and/or filter
			if (!this.view?.filter?.length > 0 && !this.view?.searchString) {
				// cleanup markers
				this.rows?.forEach(row => {
					this.columns?.forEach(column => {
						const cell = row.data.find(item => item.columnId === column.id)
						if (cell === undefined) {
							return
						}
						delete cell.searchStringFound
						delete cell.filterFound
					})
				})
				return this.rows
			}

			const data = [] // array of rows
			const searchString = this.view?.searchString

			// each row
			this.rows?.forEach(row => {
				if (debug) {
					console.debug('new row ===============================================', row)
				}
				let filterStatusRow = null
				let searchStatusRow = false

				// each column in a row => cell
				this.columns?.forEach(column => {
					if (debug) {
						console.debug('new column -------------------', column)
					}
					let filterStatus = null
					let searchStatus = true
					const filters = this.getFiltersForColumn(column)
					const cell = row.data.find(item => item.columnId === column.id)

					// if we don't have a value for this cell
					if (cell === undefined) {

						// if we have a filter for this column, but the cell is null/undefined, we can not have a match
						if (filters !== null) {
							if (debug) {
								console.debug('set row status to false (hard), cell is empty', { filters })
							}
							filterStatus = false
						}
						if (searchString) {
							searchStatus = false
						}
					} else {
						// cleanup possible old markers
						delete cell.searchStringFound
						delete cell.filterFound

						// if we should filter
						if (filters !== null) {
							filters.forEach(fil => {
								this.addMagicFieldsValues(fil)
								if (filterStatus === null || filterStatus === true) {
									filterStatus = this.isFilterFound(column, cell, fil)
								}
								// filterStatus = filterStatus || this.isFilterFound(column, cell, fil)
							})
						}
						// if we should search
						if (searchString) {
							console.debug('look for searchString', searchString)
							searchStatus = this.isSearchStringFound(column, cell, searchString)
						}

						if (debug) {
							console.debug('filterStatus for cell', { cell: cell.value, filterStatusCell: filterStatus, filterStatusRowBefore: filterStatusRow })
						}
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
			})

			return data
		},
	},

	mounted() {
		subscribe('tables:selected-rows:deselect', this.deselectAllRows)
	},
	beforeDestroy() {
		unsubscribe('tables:selected-rows:deselect', this.deselectAllRows)
	},

	methods: {
		addMagicFieldsValues(filter) {
			Object.values(this.magicFields).forEach(field => {
				const newFilterValue = filter.value.replace('@' + field.id, field.replace)
				if (filter.value !== newFilterValue) {
					filter.magicValuesEnriched = newFilterValue
				}
			})
		},
		getFiltersForColumn(column) {
			if (this.view?.filter?.length > 0) {
				const columnFilter = this.view.filter.filter(item => item.columnId === column.id)
				if (columnFilter.length > 0) {
					return columnFilter
				}
			}
			return null
		},
		isFilterFound(column, cell, filter) {
			const methodName = 'isFilterFoundFor' + this.ucfirst(column.type) + this.ucfirst(column.subtype)
			if (this[methodName] instanceof Function) {
				return this[methodName](column, cell, filter)
			}
			return false
		},
		isSearchStringFound(column, cell, searchString) {
			const methodName = 'isSearchStringFoundFor' + this.ucfirst(column.type) + this.ucfirst(column.subtype)
			if (this[methodName] instanceof Function) {
				return this[methodName](column, cell, searchString.toLowerCase())
			}

			return false
		},
		deselectAllRows() {
			this.selectedRows = []
		},
		selectAllRows(value) {
			this.selectedRows = []
			if (value) {
				this.rows.forEach(item => { this.selectedRows.push(item.id) })
			}
			this.$emit('update-selected-rows', this.selectedRows)
		},
		isRowSelected(id) {
			return this.selectedRows.includes(id)
		},
		updateRowSelection(values) {
			const id = values.rowId
			const v = values.value

			if (this.selectedRows.includes(id) && !v) {
				const index = this.selectedRows.indexOf(id)
				if (index > -1) {
					this.selectedRows.splice(index, 1)
				}
				this.$emit('update-selected-rows', this.selectedRows)
			}
			if (!this.selectedRows.includes(id) && v) {
				this.selectedRows.push(values.rowId)
				this.$emit('update-selected-rows', this.selectedRows)
			}
		},
	},
}
</script>

<style lang="scss" scoped>

:deep(table) {
	position: relative;
	border-collapse: collapse;
	border-spacing: 0;
	table-layout: auto;
	width: 100%;
	border: none;

	* {
		border: none;
	}
	// white-space: nowrap;

	td, th {
		padding-right: 8px;
	}

	td .showOnHover, th .showOnHover {
		opacity: 0;
	}

	td:hover .showOnHover, th:hover .showOnHover, .showOnHover:focus-within {
		opacity: 1;
	}

	td:not(:first-child), th:not(:first-child) {
		padding-right: 8px;
		padding-left: 8px;
	}

	tr {
		height: 51px;
		background-color: var(--color-main-background);
	}

	thead tr {
		// text-align: left;
		position: sticky;
		top: 0;
		z-index: 6;

		th {
			vertical-align: middle;
			color: var(--color-text-maxcontrast);
			box-shadow: inset 0 -1px 0 var(--color-border); // use box-shadow instead of border to be compatible with sticky heads
			background-color: var(--color-main-background-translucent);

			// always fit to title
			// min-width: max-content;
		}

	}

	tbody {

		td {
			text-align: left;
			vertical-align: middle;
			border-bottom: 1px solid var(--color-border);
		}

		tr:active, tr:hover, tr:focus, tr:hover .editor-wrapper .editor {
			background-color: var(--color-background-dark);
		}

		tr:focus-within > td:last-child {
			opacity: 1;
		}
	}

	tr>th:first-child,tr>td:first-child {
		position: sticky;
		left: 0;
		padding-left: calc(var(--default-grid-baseline) * 4);
		padding-right: calc(var(--default-grid-baseline) * 4);
		width: 60px;
		background-color: inherit;
		z-index: 5;
	}

	tr>th:last-child,tr>td:last-child {
		position: sticky;
		right: 0;
		width: 55px;
		background-color: inherit;
		padding-right: 16px;
	}

	tr>td:last-child {
		// visibility: hidden;
		opacity: 0;
	}

	tr:hover>td:last-child {
		// visibility: visible;
		opacity: 1;
	}

}

</style>
