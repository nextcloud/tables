<template>
	<div class="container">
		<table>
			<thead>
				<TableHeader :columns="columns"
					:selected-rows="selectedRows"
					:rows="rows"
					@select-all-rows="selectAllRows" />
			</thead>
			<tbody>
				<TableRow v-for="(row, index) in rows"
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

export default {
	name: 'CustomTable',
	components: {
		TableRow,
		TableHeader,
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
	},
	data() {
		return {
			selectedRows: [],
		}
	},
	methods: {
		selectAllRows(value) {
			console.debug('selectAllRows called', value)
			this.selectedRows = []
			if (value) {
				this.rows.forEach(item => { this.selectedRows.push(item.id) })
			}
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
			}
			if (!this.selectedRows.includes(id) && v) {
				this.selectedRows.push(values.rowId)
			}
		},
	},
}
</script>

<style lang="scss" scoped>

.container {
  //margin: auto;
  overflow-x: auto;
}

::v-deep table {
  position: relative;
  border-collapse: collapse;
  border-spacing: 0;
  table-layout: auto;
  width: 100%;
  border: none;

  * {
    border: none;
  }
  white-space: nowrap;

  tr {
    height: 51px;
    background-color: var(--color-main-background);
  }

  thead tr {
    text-align: left;

    th {
      vertical-align: middle;
      color: var(--color-text-maxcontrast);
    }
  }

  th, td {
    border-bottom: 1px solid var(--color-border);
  }

  tbody {

    td {
      text-align: left;
      vertical-align: middle;
    }
    tr:active, tr:hover, tr:focus {
      background-color: var(--color-background-dark);
    }
  }

  tr>th:first-child,tr>td:first-child {
    position: sticky;
    left: 0;
    width: 60px;
    padding-left: 15px;
    background-color: inherit;
  }

  tr>th:last-child,tr>td:last-child {
    position: sticky;
    right: 0;
    width: 55px;
    background-color: inherit;
    padding-right: 15px;
  }

}

</style>
