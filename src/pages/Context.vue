<!-- 1 and 3 are the ids -->
<template>
	<div>

		<NcTable v-if="dataReady" :columns="columns_1" :rows="rows_1" :table-id="1" @create-row="createRow(1, columns_1)" />
		<NcTable v-if="dataReady" :columns="columns_3" :rows="rows_3" :table-id="3" @create-row="createRow(3, columns_3)" />
		<MainModals />
	</div>
</template>

<script>
import NcTable from '../shared/components/ncTable/NcTable.vue'
import MainModals from '../modules/modals/Modals.vue'
import {tableStore} from '../store/dataId.js'
import store from '../store/store.js'
import Vuex from 'vuex'
import { mapState } from 'vuex'
import Vue from 'vue'
import { emit } from '@nextcloud/event-bus'

Vue.use(Vuex)

export default {
	components: {
		NcTable,
		MainModals,
	},

	data() {
		return {
			dataReady: false,
			ids: [1, 3],
		}
	},

	computed: {
		...mapState({
			rows_1: state => state["1"].rows,
			columns_1: state => state["1"].columns,
			rows_3: state => state["3"].rows,
			columns_3: state => state["3"].columns,
		}
		),
	},

	async beforeMount() {
		for (const id of this.ids) {
			let tableIdString = id.toString()
			store.registerModule(tableIdString, tableStore)
			await this.loadTable(id)
		}
		console.log("loaded")
		this.dataReady = true
	},

	methods: {
		async loadTable(tableId) {
			const id = tableId.toString()
			await this.$store.dispatch(id+'/getColumnsFromBE', {
				viewId: null,
				tableId: tableId
			})
			await this.$store.dispatch(id+'/loadRowsFromBE', {
				viewId: null,
				tableId: tableId
			})
		},
		createRow(tableId, columns) {
			this.$store.commit('setActiveTableId', parseInt(tableId))
			emit('tables:row:create', columns)
		},
	},
}

</script>

<style scoped lang="scss">

</style>
