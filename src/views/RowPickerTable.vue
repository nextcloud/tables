<template>
	<div class="NcTable">
		<div class="options row" style="padding-right: calc(var(--default-grid-baseline) * 2);">
			<div class="searchAndFilter">
				<SearchForm :search-string="searchTerm" @set-search-string="setSearchString" />
			</div>
		</div>
		<div class="picker-table row">
			<table>
				<thead>
					<TableHeader :columns="columns" :selected-rows="[]"
						:rows="getSearchedRows" :view="view" :view-setting="{}"
						:read-only="true" :select-row="true" />
				</thead>
				<tbody>
					<TableRow v-for="(row, index) in getSearchedRows" :key="index" :view="view"
						:row="row" :columns="columns" :selected="selectedRow === row.id" :view-setting="{}"
						:read-only="true" :select-row="true" @update-row-selection="updateRowSelection" />
				</tbody>
			</table>
		</div>
	</div>
</template>

<script>

import TableHeader from '../shared/components/ncTable/partials/TableHeader.vue'
import TableRow from '../shared/components/ncTable/partials/TableRow.vue'
import { mapGetters } from 'vuex'
import SearchForm from '../shared/components/ncTable/partials/SearchForm.vue'

export default {
	name: 'RowPickerTable',

	components: {
		TableHeader,
		TableRow,
		SearchForm,
	},

	props: {
		rows: {
			type: Array,
			default: () => [],
		},
		columns: {
			type: Array,
			default: () => [],
		},
		view: {
			type: Object,
			default: () => { },
		},
		selectedRowId: {
			type: Number,
			default: null,
		},
	},

	data() {
		return {
			selectedRow: this.selectedRowId,
			searchTerm: null,
		}
	},

	computed: {
		...mapGetters(['getColumnById']),

		getSearchedRows() {
			// if we don't have to search and/or filter
			if (!this.searchTerm) {
				// cleanup markers
				this.rows?.forEach(row => {
					this.columns?.forEach(column => {
						const cell = row.data.find(item => item.columnId === column.id)
						if (cell === undefined) {
							return
						}
						delete cell.searchStringFound
					})
				})
				return this.rows
			}

			const data = [] // array of rows

			// each row
			this.rows?.forEach(row => {
				// each column in a row => cell
				for (let i = 0; i < this.columns.length; i++) {
					const column = this.columns[i]
					let cell
					if (column.id < 0) {
						cell = { columnId: column.id }
						switch (column.id) {
						case -1:
							cell.value = row.id
							break
						case -2:
							cell.value = row.createdBy
							break
						case -3:
							cell.value = row.editedBy
							break
						case -4:
							cell.value = row.createdAt
							break
						case -5:
							cell.value = row.editedAt
							break
						}
					} else {
						cell = row.data.find(item => item.columnId === column.id)
					}

					// if we don't have a value for this cell
					if (cell !== undefined && column.isSearchStringFound(cell, this.searchTerm.toLowerCase())) {
						data.push({ ...row })
						break
					}

				}
			})
			return data
		},
	},

	methods: {
		updateRowSelection(row) {
			this.selectedRow = row.rowId
			this.$emit('update-selected-row-id', row.rowId)
		},
		setSearchString(term) {
			this.searchTerm = term
		},
	},
}
</script>

<style scoped lang="scss">
.options.row {
	padding-top: 4px; // fix to show buttons completely
	padding-bottom: 4px; // to make it nice with the padding-top
	background-color: var(--color-main-background-translucent);
}
.picker-table{
	overflow-x: scroll;
}

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

		th {
			vertical-align: middle;
			color: var(--color-text-maxcontrast);

			// sticky head
			// position: -webkit-sticky;
			// position: sticky;
			// top: 80px;
			box-shadow: inset 0 -1px 0 var(--color-border); // use box-shadow instead of border to be compatible with sticky heads
			background-color: var(--color-main-background-translucent);
			z-index: 5;

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
}
</style>
