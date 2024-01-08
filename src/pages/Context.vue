<template>
	<div>
		<NcTable :columns="table1_columns" :rows="table1_rows" />
		<NcTable :columns="table2_columns" :rows="table2_rows" />
	</div>
</template>

<script>
import NcTable from '../shared/components/ncTable/NcTable.vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { parseCol } from '../shared/components/ncTable/mixins/columnParser.js'

export default {
	components: {
		NcTable,
	},

	data() {
		return {
			table1_rows: null,
			table1_columns: null,
			table2_rows: null,
			table2_columns: null,
		}
	},

	mounted() {
		this.loadTable1()
		this.loadTable2()
	},

	methods: {
		async loadTable1() {
			let res = await axios.get(generateUrl('/apps/tables/column/table/2'))
			this.table1_columns = res.data.map(col => parseCol(col))

			res = await axios.get(generateUrl('/apps/tables/row/table/2'))
			this.table1_rows = res.data
		},
		async loadTable2() {
			let res = await axios.get(generateUrl('/apps/tables/column/table/3'))
			this.table2_columns = res.data.map(col => parseCol(col))

			res = await axios.get(generateUrl('/apps/tables/row/table/3'))
			this.table2_rows = res.data
		},
	},
}

</script>

<style scoped lang="scss">

</style>
