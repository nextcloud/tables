<template>
	<div class="container">
		<h1> Contexts </h1>
		<div v-for="context in contexts" :key="context.id" class="context">
			<div>
				<NcActionRouter :to="'/context/' + parseInt(context.id)"
					:name="context.name" />
				<p>Description: {{ context.description }}</p>
			</div>
		</div>
		<h1> All Tables and Views</h1>
		<div v-if="dataReady">
			<div v-for="table in tables" :key="table.id">
				<TableWrapper :table="table" :columns="tableData['columns' + table.id]"
					:rows="tableData['rows' + table.id]" :view-setting="viewSetting"
					@create-column="createColumn(false, table)"
					@import="openImportModal(table, false)" @download-csv="downloadCSV(table, false)" />
			</div>
			<div v-for="view in views" :key="view.id">
				<CustomView :view="view"
					:columns="tableData['view-columns' + view.id]" :rows="tableData['view-rows' + view.id]" :view-setting="viewSetting"
					@create-column="createColumn(true, view)"
					@import="openImportModal(view, true)" @download-csv="downloadCSV(view, true)" />
			</div>
			<MainModals />
		</div>
	</div>
</template>

<script>
import { NcActionRouter } from '@nextcloud/vue'
import MainModals from '../modules/modals/Modals.vue'
import Vuex, { mapState } from 'vuex'
import Vue from 'vue'
import CustomView from '../modules/main/sections/View.vue'
import TableWrapper from '../modules/main/sections/TableWrapper.vue'
import { emit } from '@nextcloud/event-bus'

Vue.use(Vuex)

export default {
	components: {
		MainModals,
		CustomView,
		TableWrapper,
		NcActionRouter,
	},

	data() {
		return {
			dataReady: false,
			viewSetting: {},
		}
	},

	computed: {
		...mapState(['tables', 'views', 'contexts']),
		tableData() {
			const data = {}
			this.tables.forEach((table, index) => {
				const rowId = 'rows' + table.id
				const colId = 'columns' + table.id
				data[colId] = this.$store.state.data.columns[(table.id).toString()]
				data[rowId] = this.$store.state.data.rows[(table.id).toString()]
			})
			this.views.forEach((view, index) => {
				const rowId = 'view-rows' + view.id
				const colId = 'view-columns' + view.id
				data[colId] = this.$store.state.data.columns['view-' + view.id]
				data[rowId] = this.$store.state.data.rows['view-' + view.id]
			})
			return data
		},
	},

	async beforeMount() {
		for (const table of this.tables) {
			await this.$store.dispatch('loadColumnsFromBE', {
				view: null,
				tableId: table.id,
			})
			await this.$store.dispatch('loadRowsFromBE', {
				viewId: null,
				tableId: table.id,
			})
		}

		for (const view of this.views) {
			await this.$store.dispatch('loadColumnsFromBE', {
				view,
				tableId: null,
			})
			await this.$store.dispatch('loadRowsFromBE', {
				viewId: view.id,
				tableId: null,
			})
		}

		this.dataReady = true
	},

	methods: {
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
	},
}

</script>

<style scoped lang="scss">
li{
	margin: 0;
	padding: 0;
	list-style: none;
}

.context {
	padding: 20px;
	border: 1px;
}

.container {
	padding: 80px;
}

h1 {
	color: red;
}

:deep(a strong) {
	text-decoration: underline;
}

:deep(li span) {
	padding: 0 !important;
	color: blue;
	font-size: 25px;
}

p {
	margin-left: 40px;
}
</style>
