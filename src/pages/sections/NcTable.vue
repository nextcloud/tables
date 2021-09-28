<template>
	<div>
		<TabulatorComponent v-model="data" :options="options" />
	</div>
</template>

<script>
import { TabulatorComponent } from 'vue-tabulator'

export default {
	name: 'NcTable',
	components: {
		TabulatorComponent,
	},
	data() {
		return {
			data: [
				{
					name: 'Test',
					age: 13,
				},
				{
					name: 'Test2',
					age: 14,
				},
				{
					name: 'Test3',
					age: 91,
				},
			],
			options: {
				resizableColumns: 'header',
				columns: [
					{
						formatter: 'rowSelection',
						titleFormatter: 'rowSelection',
						align: 'center',
						headerSort: false,
						width: 60,
					},
					{
						title: 'Name',
						field: 'name',
						sorter: 'string',
						editor: true,
					},
					{
						title: 'AGE',
						field: 'age',
						editor: false,
						// headerFilter: 'input',
					},
				],
				// footerElement: '<button>TEST</button>',
				initialSort: [
					{ column: 'age', dir: 'desc' }, // sort by this first
				],
				layout: 'fitDataFill',
			},
		}
	},
}
</script>
