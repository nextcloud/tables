<template>
	<div class="container">
		<div v-if="dataReady && context">
			<h1> Context info</h1>
			<h2> {{ context.name }}</h2>
			<p> {{ context.description }}</p>

			<h1> Context Tables</h1>
			<div v-for="table in contextTables" :key="table.id">
				<TableWrapper :table="table" :columns="tableData['columns' + table.id]" :rows="tableData['rows' + table.id]"
					:view-setting="viewSetting" @create-column="createColumn(false, table)"
					@import="openImportModal(table, false)" @download-csv="downloadCSV(table, false)" />
			</div>

			<MainModals />
		</div>
	</div>
</template>

<script>
import MainModals from '../modules/modals/Modals.vue'
import Vuex, { mapState } from 'vuex'
import Vue from 'vue'
import TableWrapper from '../modules/main/sections/TableWrapper.vue'
import { emit } from '@nextcloud/event-bus'

Vue.use(Vuex)

export default {
	components: {
		MainModals,
		TableWrapper,
	},

	data() {
		return {
			dataReady: false,
			viewSetting: {},
			context: null,
			contextTables: [],
		}
	},

	computed: {
		...mapState(['tables', 'contexts', 'activeContextId']),
		tableData() {
			const data = {}
			if (this.context && this.context.nodes) {
				for (const [, node] of Object.entries(this.context.nodes)) {
					if (node.node_type) {
						const rowId = 'rows' + node.node_id
						const colId = 'columns' + node.node_id
						data[colId] = this.$store.state.data.columns[(node.node_id).toString()]
						data[rowId] = this.$store.state.data.rows[(node.node_id).toString()]
					}
				}
			}
			return data
		},
	},

	watch: {
		async activeContextId() {
			await this.reload()
		},
	},

	async mounted() {
		await this.reload()
	},

	methods: {
		async loadContext() {
			await this.$store.dispatch('getContext', this.activeContextId)
			const index = this.contexts.findIndex(c => parseInt(c.id) === parseInt(this.activeContextId))
			this.context = this.contexts[index]

			console.debug(this.contexts, this.activeContextId)
			console.debug(this.context)
			if (this.context && this.context.nodes) {
				for (const [, node] of Object.entries(this.context.nodes)) {
					if (node.node_type === 'table') {
						const table = this.tables.find(table => table.id === node.node_id)
						this.contextTables.push(table)
						await this.$store.dispatch('loadColumnsFromBE', {
							view: null,
							tableId: node.node_id,
						})
						await this.$store.dispatch('loadRowsFromBE', {
							viewId: null,
							tableId: node.node_id,
						})
					}
				}
			}
		},
		createColumn(isView, element) {
			emit('tables:column:create', { isView, element })
		},
		downloadCSV(element, isView) {
			const rowId = !isView ? 'rows' + element.id : 'view-rows' + element.id
			const colId = !isView ? 'columns' + element.id : 'view-columns' + element.id
			this.downloadCsv(this.tableData[rowId], this.tableData[colId], element.title)
		},
		openImportModal(element, isView) {
			emit('tables:modal:import', { element, isView })
		},

		async reload() {
			if (this.activeContextId) {
				await this.loadContext()
				this.dataReady = true
			}
		},
	},
}

</script>

<style scoped lang="scss">
.container {
	padding: 80px;
}
</style>
