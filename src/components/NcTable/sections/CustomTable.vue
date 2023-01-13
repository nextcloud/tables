<template>
	<div class="container">
		<table>
			<thead>
				<TableHeader :columns="columns" />
			</thead>
			<tbody>
				<TableRow v-for="(row, index) in rows"
					:key="index"
					:row="row"
					:columns="columns" />
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
    width: 50px;
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
