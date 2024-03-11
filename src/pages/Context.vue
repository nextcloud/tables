<template>
	<div class="row">
		<div v-if="loading" class="icon-loading" />
		<div v-if="!loading && context">
			<div class="content first-row">
				<div class="row">
					<h1> {{ context.iconName }}&nbsp; {{ context.name }}</h1>
				</div>
				<div class="row">
					<h3> {{ context.description }}</h3>
				</div>
			</div>

			<div class="resources">
				<div v-for="resource in contextResources" :key="resource.key">
					<div v-if="!resource.isView">
						<TableWrapper :table="resource" :columns="columns[resource.key]" :rows="rows[resource.key]"
							:view-setting="viewSetting" @create-column="createColumn(false, resource)"
							@import="openImportModal(resource, false)" @download-csv="downloadCSV(resource, false)" />
					</div>
					<div v-else-if="resource.isView">
						<CustomView :view="resource" :columns="columns[resource.key]" :rows="rows[resource.key]"
							:view-setting="viewSetting" @create-column="createColumn(true, resource)"
							@import="openImportModal(resource, true)" @download-csv="downloadCSV(resource, true)" />
					</div>
				</div>
			</div>

			<MainModals />
		</div>
	</div>
</template>

<script>
import MainModals from '../modules/modals/Modals.vue'
import Vuex, { mapState, mapGetters } from 'vuex'
import Vue from 'vue'
import TableWrapper from '../modules/main/sections/TableWrapper.vue'
import CustomView from '../modules/main/sections/View.vue'
import { emit } from '@nextcloud/event-bus'
import { NODE_TYPE_TABLE, NODE_TYPE_VIEW } from '../shared/constants.js'
import exportTableMixin from '../shared/components/ncTable/mixins/exportTableMixin.js'

Vue.use(Vuex)

export default {
	components: {
		MainModals,
		TableWrapper,
		CustomView,
	},

	mixins: [exportTableMixin],

	data() {
		return {
			loading: true,
			viewSetting: {},
			context: null,
			contextResources: [],
		}
	},

	computed: {
		...mapState(['tables', 'contexts', 'activeContextId', 'views']),
		...mapGetters(['activeContext']),
		rows() {
			const rows = {}
			if (this.context && this.context.nodes) {
				for (const [, node] of Object.entries(this.context.nodes)) {
					if (parseInt(node.node_type) === NODE_TYPE_TABLE) {
						const rowId = (node.node_id).toString()
						rows[rowId] = this.$store.state.data.rows[rowId]

					} else if (parseInt(node.node_type) === NODE_TYPE_VIEW) {
						const rowId = 'view-' + (node.node_id).toString()
						rows[rowId] = this.$store.state.data.rows[rowId]
					}
				}
			}
			return rows

		},

		columns() {
			const columns = {}
			if (this.context && this.context.nodes) {
				for (const [, node] of Object.entries(this.context.nodes)) {
					if (parseInt(node.node_type) === NODE_TYPE_TABLE) {
						const columnId = (node.node_id).toString()
						columns[columnId] = this.$store.state.data.columns[columnId]
					} else if (parseInt(node.node_type) === NODE_TYPE_VIEW) {
						const columnId = 'view-' + (node.node_id).toString()
						columns[columnId] = this.$store.state.data.columns[columnId]

					}

				}
			}
			return columns
		},
	},

	watch: {
		async activeContextId() {
			if (this.activeContextId && !this.activeContext) {
				// context does not exists, go to startpage
				this.$router.push('/').catch(err => err)
			} else {
				await this.reload()
			}
		},
	},

	async mounted() {
		await this.reload()
	},

	methods: {
		async reload() {
			this.loading = true
			if (this.activeContextId) {
				await this.loadContext()
			}
			this.loading = false
		},
		async loadContext() {
			this.contextResources = []
			await this.$store.dispatch('getContext', { id: this.activeContextId })
			const index = this.contexts.findIndex(c => parseInt(c.id) === parseInt(this.activeContextId))
			this.context = this.contexts[index]

			if (this.context && this.context.nodes) {
				for (const [, node] of Object.entries(this.context.nodes)) {
					const nodeType = parseInt(node.node_type)
					if (nodeType === NODE_TYPE_TABLE) {
						const table = this.tables.find(table => table.id === node.node_id)
						await this.$store.dispatch('loadColumnsFromBE', {
							view: null,
							tableId: table.id,
						})
						await this.$store.dispatch('loadRowsFromBE', {
							viewId: null,
							tableId: table.id,
						})
						table.key = (table.id).toString()
						table.isView = false
						this.contextResources.push(table)
					} else if (nodeType === NODE_TYPE_VIEW) {
						const view = this.views.find(view => view.id === node.node_id)
						await this.$store.dispatch('loadColumnsFromBE', {
							view,
							tableId: view.tableId,
						})
						await this.$store.dispatch('loadRowsFromBE', {
							viewId: view.id,
							tableId: view.tableId,
						})
						view.key = 'view-' + (view.id).toString()
						view.isView = true
						this.contextResources.push(view)
					}
				}
			}
		},
		createColumn(isView, element) {
			emit('tables:column:create', { isView, element })
		},
		downloadCSV(element, isView) {
			const rowId = !isView ? element.key : 'view-' + element.key
			const colId = !isView ? element.key : 'view-' + element.key
			this.downloadCsv(this.rows[rowId], this.columns[colId], element.title)
		},
		openImportModal(element, isView) {
			emit('tables:modal:import', { element, isView })
		},
	},
}

</script>

<style scoped lang="scss">
.content {
	margin: 50px;
}
.resource, content {
	padding: 30px 0;
}
</style>
