<template>
	<div>
		<TabulatorComponent v-model="data" :options="options2" />
	</div>
</template>

<script>
import { TabulatorComponent } from 'vue-tabulator'

export default {
	name: 'NcTable',
	components: {
		TabulatorComponent,
	},
	props: {
		columns: {
			type: Array,
			default: null,
		},
	},
	data() {
		return {
			data: [],
			options: {
				resizableColumns: 'header',
				columns: this.columnsDefinition,
				// footerElement: '<button>TEST</button>',
				// initialSort: [
				// { column: 'age', dir: 'desc' }, // sort by this first
				// ],
				layout: 'fitDataFill',
			},
		}
	},
	computed: {
		columnsDefinition() {
			const def = [
				{
					formatter: 'rowSelection',
					titleFormatter: 'rowSelection',
					align: 'center',
					headerSort: false,
					width: 60,
				},
			]
			if (this.columns) {
				this.columns.forEach(item => {
					def.push({
						title: item.title,
						field: 'column-' + item.id,
						editor: true,
					})
				})
			}
			console.debug('columns definition array', def)
			return def
		},
		options2() {
			return {
				resizableColumns: 'header',
				columns: this.columnsDefinition,
				// footerElement: '<button>TEST</button>',
				// initialSort: [
				// { column: 'age', dir: 'desc' }, // sort by this first
				// ],
				layout: 'fitDataFill',
			}
		},
	},
}
</script>
